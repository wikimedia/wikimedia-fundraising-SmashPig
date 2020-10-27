<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\AdyenMessage;

/**
 * Job that merges a capture IPN message from Adyen with donor info from the
 * pending database, then places that into the donations queue.
 *
 * Class RecordCaptureJob
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class RecordCaptureJob extends RunnableJob {

	protected $account;
	protected $currency;
	protected $amount;
	protected $gatewayTxnId;
	protected $merchantReference;
	protected $eventDate;

	public static function factory( AdyenMessage $ipnMessage ) {
		$obj = new RecordCaptureJob();

		$obj->account = $ipnMessage->merchantAccountCode;
		$obj->currency = $ipnMessage->currency;
		$obj->amount = $ipnMessage->amount;
		$obj->gatewayTxnId = $ipnMessage->getGatewayTxnId();
		$obj->merchantReference = $ipnMessage->merchantReference;
		$obj->eventDate = $ipnMessage->eventDate;

		return $obj;
	}

	public function execute() {
		$logger = Logger::getTaggedLogger( "corr_id-adyen-{$this->merchantReference}" );
		$logger->info(
			"Recording successful capture on account '{$this->account}' with authorization reference " .
				"'{$this->gatewayTxnId}' and order ID '{$this->merchantReference}'."
		);

		// Find the details from the payment site in the pending database.
		$logger->debug( 'Attempting to locate associated message in pending database' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'adyen', $this->merchantReference );

		if ( $dbMessage && ( isset( $dbMessage['order_id'] ) ) ) {
			$logger->debug( 'A valid message was obtained from the pending queue' );

			// Add the gateway transaction ID and send it to the completed queue
			$dbMessage['gateway_txn_id'] = $this->gatewayTxnId;
			// Use the eventDate from the capture as the date
			$dbMessage['date'] = strtotime( $this->eventDate );

			QueueWrapper::push( 'donations', $dbMessage );

			// Remove it from the pending database
			$logger->debug( 'Removing donor details message from pending database' );
			$db->deleteMessage( $dbMessage );

		} else {
			// Sometimes we don't have a pending db row because the donor made
			// multiple attempts with the same order ID. It would be nice if
			// Adyen could prevent that, but let's not send a failmail since
			// we'll eventually get the donor details from the payments log
			// when we parse the audit.
			$logger->warning(
				"Could not find donor details for authorization Reference '{$this->gatewayTxnId}' " .
					"and order ID '{$this->merchantReference}'.",
				$dbMessage
			);
		}

		return true;
	}
}
