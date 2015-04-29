<?php namespace SmashPig\PaymentProviders\Astropay\ExpatriatedMessages;

/**
 * Message indicating a successfult payment
 */
class PaymentMessage extends AstropayMessage {
	public function getDestinationQueue() {
		return 'verified';
	}
}
