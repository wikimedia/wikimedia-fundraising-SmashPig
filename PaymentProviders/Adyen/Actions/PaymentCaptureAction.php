<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Configuration;
use SmashPig\Core\Jobs\DeletePendingJob;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;

/**
 * When an authorization message from Adyen comes in, we need to either place
 * a capture request into the job queue, or we need to slay its orphan because
 * the transaction failed.
 */
class PaymentCaptureAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {
		$tl = new TaggedLogger( 'PaymentCaptureAction' );

		if ( $msg instanceof Authorisation ) {
			$jobQueueObj = Configuration::getDefaultConfig()->object( 'data-store/jobs' );
			if ( $msg->success ) {
				// Here we need to capture the payment, the job runner will collect the
				// orphan message
				$tl->info(
					"Adding Adyen capture job for {$msg->currency} {$msg->amount} " .
					"with id {$msg->correlationId} and psp reference {$msg->pspReference}."
				);
				$jobQueueObj->addObject(
					ProcessCaptureRequestJob::factory( $msg )
				);

			} else {
				// And here we just need to destroy the orphan
				$tl->info(
					"Adyen payment with correlation id {$msg->correlationId} " .
					"reported status failed: '{$msg->reason}'. " .
					'Queueing job to delete pending records.'
				);
				$jobQueueObj->addObject(
					new DeletePendingJob(
						'adyen',
						$msg->merchantReference,
						$msg->correlationId
					)
				);
			}
		}

		return true;
	}
}
