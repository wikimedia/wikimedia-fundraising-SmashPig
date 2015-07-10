<?php namespace SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages;

/**
 * Message indicating a successfult payment
 */
class PaymentMessage extends AstroPayMessage {
	public function getDestinationQueue() {
		return 'verified';
	}
}
