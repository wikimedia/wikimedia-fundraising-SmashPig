<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\Core\Logging\Logger;
use SmashPig\CrmLink\Messages\DonationInterfaceMessage;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture;

/**
 * Job that merges a capture IPN message from Adyen with donor info from the
 * pending database, then places that into the verified queue.
 *
 * Class RecordCaptureJob
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class RecordCaptureJob extends RunnableJob {

	protected $account;
	protected $currency;
	protected $amount;
	protected $originalReference;
	protected $merchantReference;

	public static function factory( Capture $captureMessage ) {
		$obj = new RecordCaptureJob();

		$obj->correlationId = $captureMessage->correlationId;
		$obj->account = $captureMessage->merchantAccountCode;
		$obj->currency = $captureMessage->currency;
		$obj->amount = $captureMessage->amount;
		$obj->originalReference = $captureMessage->originalReference;
		$obj->merchantReference = $captureMessage->merchantReference;

		return $obj;
	}

	public function execute() {
		$logger = Logger::getTaggedLogger( "corr_id-adyen-{$this->merchantReference}" );
		$logger->info(
			"Recording successful capture on account '{$this->account}' with authorization reference " .
				"'{$this->originalReference}' and order ID '{$this->merchantReference}'."
		);

		$config = Configuration::getDefaultConfig();

		// Find the details from the payment site in the pending database.
		$logger->debug( 'Attempting to locate associated message in pending database' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'adyen', $this->merchantReference );

		if ( $dbMessage && ( isset( $dbMessage['order_id'] ) ) ) {
			$logger->debug( 'A valid message was obtained from the pending queue' );

			// Add the gateway transaction ID and send it to the completed queue
			$dbMessage['gateway_txn_id'] = $this->originalReference;
			$queueMessage = DonationInterfaceMessage::fromValues( $dbMessage );
			SourceFields::addToMessage( $queueMessage );
			$config->object( 'data-store/verified' )->push( $queueMessage );

			// Remove it from the pending database
			$logger->debug( 'Removing donor details message from pending database' );
			$db->deleteMessage( $dbMessage );

		} else {
			$logger->error(
				"Could not find a processable message for authorization Reference '{$this->originalReference}' " .
					"and order ID '{$this->merchantReference}'.",
				$dbMessage
			);
		}

		return true;
	}
}
