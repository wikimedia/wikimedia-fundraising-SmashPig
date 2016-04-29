<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Configuration;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\Core\Logging\Logger;
use SmashPig\CrmLink\Messages\DonationInterfaceMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture;

/**
 * Job that merges a capture IPN message from Adyen with donor info from the
 * pending queue, then places that into the verified queue.
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

	public static function factory( Capture $captureMessage ) {
		$obj = new RecordCaptureJob();

		$obj->correlationId = $captureMessage->correlationId;
		$obj->account = $captureMessage->merchantAccountCode;
		$obj->currency = $captureMessage->currency;
		$obj->amount = $captureMessage->amount;
		$obj->originalReference = $captureMessage->originalReference;

		return $obj;
	}

	public function execute() {
		$logger = Logger::getTaggedLogger( "corr_id-{$this->correlationId}" );
		$logger->info(
			"Recording successful capture on account '{$this->account}' with authorization reference " .
				"'{$this->originalReference}' and correlation id '{$this->correlationId}'."
		);

		$config = Configuration::getDefaultConfig();
		// Find the details from the payment site in the pending queue.
		$logger->debug( 'Attempting to locate associated message in pending queue' );
		$pendingQueue = $config->object( 'data-store/pending' );
		$queueMessage = $pendingQueue->queueGetObject( null, $this->correlationId );

		if ( $queueMessage && ( $queueMessage instanceof DonationInterfaceMessage ) ) {
			$logger->debug( 'A valid message was obtained from the pending queue' );

			// Add the gateway transaction ID and send it to the completed queue
			$queueMessage->gateway_txn_id = $this->originalReference;
			$config->object( 'data-store/verified' )->addObject( $queueMessage );

			// Remove it from the pending queue
			$logger->debug( "Removing all references to donation in pending queue" );
			$pendingQueue->queueAckObject();
			$pendingQueue->removeObjectsById( $this->correlationId );

		} else {
			$logger->error(
				"Could not find a processable message for authorization Reference '{$this->originalReference}' " .
					"and correlation ID '{$this->correlationId}'.",
				$queueMessage
			);
		}

		return true;
	}
}
