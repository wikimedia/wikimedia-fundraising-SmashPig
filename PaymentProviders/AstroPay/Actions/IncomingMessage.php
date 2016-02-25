<?php namespace SmashPig\PaymentProviders\AstroPay\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

class IncomingMessage implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {

		$destinationQueue = $msg->getDestinationQueue();

		if ( $destinationQueue ) {
			$queue = Context::get()->getConfiguration()->object( "data-store/{$destinationQueue}" );
			$queueMsg = $msg->normalizeForQueue();

			$queue->addObject( $queueMsg );
		} else {
			$class = get_class( $msg );
			Logger::warning( "Ignoring message of type {$class}", $msg );
		}

		return true;
	}
}
