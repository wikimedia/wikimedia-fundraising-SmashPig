<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RefundWithData;
use SmashPig\Core\Logging\Logger;

/**
 * Action for a refund! whoo!
 */
class RefundInitiatedAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {
		Logger::enterContext( 'RefundInitiatedAction' );

		if ( $msg instanceof RefundWithData ) {
			// I've never even seen one of these messages so we'll just have to wait
			// and see
			Logger::error(
				"Oh hai! We got a refund on pspReference '{$msg->pspReference}' with correlation id '" .
					"{$msg->correlationId}'! What do we do now?",
				$msg
			);
		}

		Logger::leaveContext();
		return true;
	}
}
