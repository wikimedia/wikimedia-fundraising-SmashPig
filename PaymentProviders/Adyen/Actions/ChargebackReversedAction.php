<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\ChargebackReversed;

/**
 * Action to fire when an iniated chargeback is canceled.
 */
class ChargebackReversedAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'ChargebackInitiatedAction' );

		if ( $msg instanceof ChargebackReversed ) {
			// I've never even seen one of these messages so we'll just have to wait
			// and see
			$tl->error(
				"Oh hai! We got a chargeback reversal on pspReference " .
				"'{$msg->pspReference}'! What do we do now?",
				$msg
			);
		}

		return true;
	}
}
