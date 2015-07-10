<?php namespace SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages;

/**
 * Message indicating a payment has expired
 */
class ExpirationMessage extends AstroPayMessage {
	function getDestinationQueue() {
		return null;
	}
}
