<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Jobs\DeletePendingJob;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Adyen\Jobs\RecordCaptureJob;

/**
 * When an authorization message from Adyen comes in, we need to either place
 * a capture request into the job queue, or we need to slay its orphan because
 * the transaction failed.
 */
class PaymentCaptureAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'PaymentCaptureAction' );

		if ( $msg instanceof Authorisation ) {
			if ( $msg->success ) {
				// Ignore subsequent recurring IPNs
				if ( $msg->isRecurringInstallment() ) {
					return true;
				}
				// For iDEAL, treat this as the final notification of success. We don't
				// need to make any more API calls, just record it in Civi.
				if ( isset( $msg->paymentMethod ) && $msg->paymentMethod == 'ideal' ) {
					$tl->info(
						"Adding Adyen record capture job for {$msg->currency} {$msg->amount} " .
						"with psp reference {$msg->pspReference}."
					);
					$job = RecordCaptureJob::factory( $msg );
					QueueWrapper::push( 'jobs-adyen', $job );
				} else {
					// Here we need to capture the payment, the job runner will collect the
					// orphan message
					$tl->info(
						"Adding Adyen capture job for {$msg->currency} {$msg->amount} " .
						"with psp reference {$msg->pspReference}."
					);
					$job = ProcessCaptureRequestJob::factory( $msg );
					QueueWrapper::push( 'jobs-adyen', $job );
				}
			} else {
				// And here we just need to destroy the orphan
				// FIXME: should we really delete these details, if donors can
				// potentially re-use a merchant reference by reloading an Adyen
				// full-redirect page?
				$tl->info(
					"Adyen payment with psp reference {$msg->pspReference} " .
					"reported status failed: '{$msg->reason}'. " .
					'Queueing job to delete pending records.'
				);
				$job = DeletePendingJob::factory(
					'adyen',
					$msg->merchantReference
				);
				QueueWrapper::push( 'jobs-adyen', $job );
			}
		}

		return true;
	}
}
