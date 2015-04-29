<?php namespace SmashPig\PaymentProviders\Astropay\ExpatriatedMessages;

/**
 * Message indicating a payment has expired
 */
class ExpirationMessage extends AstropayMessage {
	function getDestinationQueue() {
		return null;
	}
}
