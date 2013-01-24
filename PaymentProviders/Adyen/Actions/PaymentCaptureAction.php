<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Configuration;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\Core\DataStores\KeyedOpaqueDataStore;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\Core\Logging\Logger;

/**
 * When an authorization message from Adyen comes in, we need to either place
 * a capture request into the job queue, or we need to slay it's orphan because
 * the transaction failed.
 */
class PaymentCaptureAction implements IListenerMessageAction {

	/** @var KeyedOpaqueDataStore Queue for job objects */
	protected $jobQueueObj;

	/** @var KeyedOpaqueDataStore Queue where limbo transactions went */
	protected $limboQueueObj;

	public function __construct() {
		$this->jobQueueObj = Configuration::getDefaultConfig()->obj( 'data-store/jobs' );
		$this->limboQueueObj = Configuration::getDefaultConfig()->obj( 'data-store/limbo' );
	}

	public function execute( ListenerMessage $msg ) {
		if ( $msg instanceof Authorisation ) {
			if ( $msg->success ) {
				// Here we need to capture the payment, the job runner will collect the
				// orphan message
				Logger::info(
					"Adding Adyen capture job for {$msg->currency} {$msg->amount} with id {$msg->correlationId} and psp reference {$msg->pspReference}."
				);
				$this->jobQueueObj->addObject(
					ProcessCaptureRequestJob::factory(
						$msg->correlationId,
						$msg->merchantAccountCode,
						$msg->currency,
						$msg->amount,
						$msg->pspReference
					)
				);

			} else {
				// And here we just need to destroy the orphan
				Logger::info(
					"Adyen payment with correlation id {$msg->correlationId} reported status failed: '{$msg->reason}'. Deleting orphans."
				);
				do {
					$limboMsg = $this->limboQueueObj->queueGetObject( null, $msg->correlationId );
					if ( $limboMsg ) {
						Logger::info( "Deleting orphan.", $limboMsg );
						$this->limboQueueObj->queueAckObject();
					}
				} while( $limboMsg != null );
			}
		}

		return true;
	}

}
