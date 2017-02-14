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
	}
}
