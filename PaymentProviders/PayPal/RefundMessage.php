<?php

namespace SmashPig\PaymentProviders\PayPal;

class RefundMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {
		$message['gateway_refund_id'] = $ipnMessage['txn_id'];
		$message['gross_currency'] = $ipnMessage['mc_currency'];
		if ( isset( $message['type'] ) &&
			$message['type'] === 'chargeback_settlement' ) {
			$message['type'] = 'chargeback';
		} else {
			$message['type'] = 'refund';
		}

		// Express checkout puts a description in transaction_subject, Legacy puts a contribution
		// tracking ID there. Chargebacks don't set the field at all.
		if ( isset( $ipnMessage['transaction_subject'] ) && !is_numeric( $ipnMessage['transaction_subject'] ) ) {
			$message['gateway'] = 'paypal_ec';
		} else {
			$message['gateway'] = 'paypal';
		}

	}
}
