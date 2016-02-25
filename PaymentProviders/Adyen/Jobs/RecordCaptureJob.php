<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Configuration;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\Core\Logging\Logger;
use SmashPig\CrmLink\Messages\DonationInterfaceMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture;

/**
 * Job that merges capture IPN calls from Adyen with a pending message in the
 * queue and then places that into the verified queue.
 *
 * Class RecordCaptureJob
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class RecordCaptureJob extends RunnableJob {

	protected $account;
	protected $currency;
	protected $amount;
	protected $pspReference;

	public static function factory( Capture $captureMessage ) {
		$obj = new RecordCaptureJob();

		$obj->correlationId = $captureMessage->correlationId;
		$obj->account = $captureMessage->merchantAccountCode;
		$obj->currency = $captureMessage->currency;
		$obj->amount = $captureMessage->amount;
		$obj->pspReference = $captureMessage->pspReference;

		return $obj;
	}

	public function execute() {
		Logger::enterContext( "corr_id-{$this->correlationId}" );
		Logger::info(
			"Recording successful capture on account '{$this->account}' with reference '{$this->pspReference}' " .
			"and correlation id '{$this->correlationId}'."
		);

		$config = Configuration::getDefaultConfig();
		// Find the details from the payment site in the pending queue.
		Logger::debug( 'Attempting to locate associated message in pending queue' );
		$pendingQueue = $config->object( 'data-store/pending' );
		$queueMessage = $pendingQueue->queueGetObject( null, $this->correlationId );

		if ( $queueMessage && ( $queueMessage instanceof DonationInterfaceMessage ) ) {
			Logger::debug( 'A valid message was obtained from the pending queue' );

			// Add the gateway transaction ID and send it to the completed queue
			$queueMessage->gateway_txn_id = $this->pspReference;
			$config->object( 'data-store/verified' )->addObject( $queueMessage );

			// Remove it from the pending queue
			Logger::debug( "Removing all references to donation in pending queue" );
			$pendingQueue->queueAckObject();
			$pendingQueue->removeObjectsById( $this->correlationId );

		} else {
			Logger::error(
				"Could not find a processable message for PSP Reference '{$this->pspReference}' and correlation ".
					"ID '{$this->correlationId}'.",
				$queueMessage
			);
		}

		Logger::leaveContext();
		return true;
	}
}
