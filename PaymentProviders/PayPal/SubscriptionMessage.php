<?php

namespace SmashPig\PaymentProviders\PayPal;

class SubscriptionMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {
		// Preserve existing logic for now
		$message = $ipnMessage;
	}
}
