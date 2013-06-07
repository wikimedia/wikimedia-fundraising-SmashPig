<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\ChargebackReversed;
use SmashPig\Core\Logging\Logger;

/**
 * Action to fire when an iniated chargeback is canceled.
 */
class ChargebackReversedAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {
		Logger::enterContext( 'ChargebackInitiatedAction' );

		if ( $msg instanceof ChargebackReversed ) {
			// I've never even seen one of these messages so we'll just have to wait
			// and see
			Logger::error(
				"Oh hai! We got a chargeback reversal on pspReference '{$msg->pspReference}' with correlation id '" .
					"{$msg->correlationId}'! What do we do now?",
				$msg
			);
		}

		Logger::leaveContext();
		return true;
	}
}
