<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Chargeback;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\NotificationOfChargeback;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RequestForInformation;
use SmashPig\Core\Logging\Logger;

/**
 * When any kind of chargeback initiated (or completion) message arrives, this will
 * be fired.
 */
class ChargebackInitiatedAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {
		Logger::enterContext( 'ChargebackInitiatedAction' );

		if ( $msg instanceof Chargeback ||
			 $msg instanceof NotificationOfChargeback ||
			 $msg instanceof RequestForInformation
		) {
			// I've never even seen one of these messages so we'll just have to wait
			// and see
			Logger::error(
				"Oh hai! We got a chargeback on pspReference '{$msg->pspReference}' with correlation id '" .
					"{$msg->correlationId}'! What do we do now?",
				$msg
			);
		}

		Logger::leaveContext();
		return true;
	}
}
