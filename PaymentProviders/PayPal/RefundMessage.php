<?php

namespace SmashPig\PaymentProviders\PayPal;

class RefundMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {
		$message['gateway_refund_id'] = $ipnMessage['txn_id'];
		$message['gross_currency'] = $ipnMessage['mc_currency'];
		if ( isset( $message['txn_type'] ) && $message['txn_type'] === 'adjustment' ) {
			$message['type'] = 'chargeback';

			// For chargebacks, express checkout sets the 'invoice' field
			if ( isset( $ipnMessage['invoice'] ) ) {
				$message['gateway'] = 'paypal_ec';
			} else {
				$message['gateway'] = 'paypal';
			}
		} elseif ( isset( $ipnMessage['reason_code'] ) && $ipnMessage['reason_code'] === 'refund' ) {
			$message['type'] = 'refund';

			// For refunds, express checkout puts a description in transaction_subject,
			// but legacy puts a contribution tracking ID there.
			if ( isset( $ipnMessage['transaction_subject'] ) && !is_numeric( $ipnMessage['transaction_subject'] ) ) {
				$message['gateway'] = 'paypal_ec';
			} else {
				$message['gateway'] = 'paypal';
			}
		}
	}
}
