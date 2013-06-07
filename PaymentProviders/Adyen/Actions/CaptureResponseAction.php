<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture;
use SmashPig\Core\Logging\Logger;

/**
 * Action that takes place after a Capture modification request has completed.
 *
 * @package SmashPig\PaymentProviders\Adyen\Actions
 */
class CaptureResponseAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {
		Logger::enterContext( 'CaptureResponseAction' );

		if ( $msg instanceof Capture ) {
			if ( !$msg->success ) {
				// Crap; we've already recorded that this message has succeeded; guess
				// we should just send an email saying hey! something unexpected happened!
				Logger::error(
					"Payment with reference {$msg->pspReference} and correlation id {$msg->correlationId} previously " .
						"recorded as a success has failed to be successfully captured! Fix your records!",
					$msg
				);
			}
			/* else {
				// TODO: This is probably where we should record payment, but I don't want to
				// add the complexity of a second job at this time.
			}
			*/
		}

		Logger::leaveContext();
		return true;
	}
}