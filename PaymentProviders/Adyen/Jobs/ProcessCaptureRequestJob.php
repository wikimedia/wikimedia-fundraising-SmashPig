<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\PaymentProviders\Adyen\AdyenPaymentsAPI;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Configuration;
use SmashPig\CrmLink\Messages\DonationInterfaceMessage;

/**
 * Job that merges inbound IPN calls from Adyen with a limbo message in the queue
 * and then places that into the verified queue. Is idempotent with respect to the
 * limbo queue state -- e.g. if no limbo message is found it assumes that the message
 * was already processed.
 *
 * Class ProcessCaptureRequestJob
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class ProcessCaptureRequestJob extends RunnableJob {

	protected $account;
	protected $currency;
	protected $amount;
	protected $pspReference;

	public static function factory( $correlationId, $account, $currency, $amount, $pspReference ) {
		$obj = new ProcessCaptureRequestJob();

		$obj->correlationId = $correlationId;
		$obj->account = $account;
		$obj->currency = $currency;
		$obj->amount = $amount;
		$obj->pspReference = $pspReference;

		return $obj;
	}

	public function execute() {
		Logger::enterContext( "corr_id-$this->correlationId" );
		Logger::info(
			"Attempting to capture payment on account '{$this->account}' with reference '{$this->pspReference}' " .
			"and correlation id '{$this->correlationId}'."
		);

		// Determine if a message exists in the pending queue; if it does then we haven't
		// processed this particular transaction before
		Logger::debug( 'Attempting to locate associated message in pending queue' );
		$pendingQueue = Configuration::getDefaultConfig()->obj( 'data-store/pending' );
		$queueMessage = $pendingQueue->queueGetObject( null, $this->correlationId );

		if ( $queueMessage && ( $queueMessage instanceof DonationInterfaceMessage ) ) {
			Logger::debug( 'A valid message was obtained from the pending queue' );

			// Attempt to capture the payment
			$api = new AdyenPaymentsAPI( $this->account );
			$captureResult = $api->capture( $this->currency, $this->amount, $this->pspReference );

			if ( $captureResult ) {
				// Success! Queue it as completed
				Logger::info( "Successfully captured payment! Returned reference: '{$captureResult}'" );
				Configuration::getDefaultConfig()->obj( 'data-store/verified' )->addObject( $queueMessage );

			} else {
				// Crap; couldn't capture it. Log it!
				Logger::error(
					"Failed to capture payment on account '{$this->account}' with reference " .
						"'{$this->pspReference}' and correlation id '{$this->correlationId}'. This " .
						"message will be removed from the queues. Error return was: '{$captureResult}'",
					$queueMessage
				);
			}

			// Remove it from all the queues
			Logger::debug( "Removing all references to donation in pending and limbo queues" );
			$pendingQueue->queueAckObject();
			$pendingQueue->removeObjectsById( $this->correlationId );
			Configuration::getDefaultConfig()->obj( 'data-store/limbo' )->removeObjectsById( $this->correlationId );

		} else {
			Logger::warning(
				"Could not find a processable message for PSP Reference '{$this->pspReference}' and correlation ".
					"ID '{$this->correlationId}'.",
				$queueMessage
			);
		}

		Logger::leaveContext();
		return true;
	}
}
