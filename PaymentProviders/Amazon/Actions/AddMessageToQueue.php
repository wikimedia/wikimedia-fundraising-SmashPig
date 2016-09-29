<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

class AddMessageToQueue implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {
		// FIXME: I don't like this dispatch style
		$destinationQueue = $msg->getDestinationQueue();

		if ( $destinationQueue ) {
			$queue = Context::get()->getConfiguration()->object( "data-store/{$destinationQueue}" );
			$queueMsg = $msg->normalizeForQueue();

			$queue->push( $queueMsg );
		} else {
			$class = get_class( $msg );
			Logger::warning( "Ignoring message of type {$class}", $msg );
		}

		return true;
	}
}
