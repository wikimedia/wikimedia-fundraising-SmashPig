<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Configuration;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture;
use SmashPig\PaymentProviders\Adyen\Jobs\RecordCaptureJob;

/**
 * Action that takes place after a Capture modification request has completed.
 *
 * @package SmashPig\PaymentProviders\Adyen\Actions
 */
class CaptureResponseAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {
		$tl = new TaggedLogger( 'CaptureResponseAction' );

		if ( $msg instanceof Capture ) {
			if ( $msg->success ) {
				$tl->info(
					"Adding record capture job for {$msg->currency} {$msg->amount} with id {$msg->correlationId} and psp reference {$msg->pspReference}."
				);
				$recordJob = RecordCaptureJob::factory( $msg );
				$jobQueue = Configuration::getDefaultConfig()->object( 'data-store/jobs' );
				$jobQueue->addObject( $recordJob );
			} else {
				$tl->warning(
					"Capture failed for payment with reference {$msg->pspReference} and correlation id {$msg->correlationId}.",
					$msg
				);
			}
		}

		return true;
	}
}
