<?php

namespace SmashPig\PaymentProviders\PayPal;

class PaymentMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {
		if ( $ipnMessage['txn_type'] === 'express_checkout' ) {
			$message['gateway'] = 'paypal_ec';
		} else {
			$message['gateway'] = 'paypal';
		}

		// TODO: Implement normalizeMessage() method.

	}
}
