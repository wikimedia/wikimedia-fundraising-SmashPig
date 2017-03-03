<?php

namespace SmashPig\PaymentProviders\PayPal;

class SubscriptionMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {

		$message['recurring'] = "1";

		if ( isset( $ipnMessage['payment_date'] ) ) {
			$message['date'] = strtotime( $ipnMessage['payment_date'] );
		}

		// the subscription id
		$message['subscr_id'] = $ipnMessage['subscr_id'];
		$message['txn_type'] = $ipnMessage['txn_type'];
		$message['email'] = $ipnMessage['payer_email'];

		// Contact info
		if ( $ipnMessage['txn_type'] === 'subscr_signup' || $ipnMessage['txn_type'] === 'subscr_payment' || $ipnMessage['txn_type'] === 'subscr_modify' ) {
			$message['first_name'] = $ipnMessage['first_name'];
			$message['middle_name'] = '';
			$message['last_name'] = $ipnMessage['last_name'];

			if ( isset( $ipnMessage['address_street'] ) ) {
				$split = explode("\n", str_replace("\r", '', $ipnMessage['address_street']));
				$message['street_address'] = $split[0];
				if ( count( $split ) > 1 ) {
					$message['supplemental_address_1'] = $split[1];
				}
				$message['city'] = $ipnMessage['address_city'];
				$message['state_province'] = $ipnMessage['address_state'];
				$message['country'] = $ipnMessage['address_country_code'];
				$message['postal_code'] = $ipnMessage['address_zip'];

			} elseif ( isset( $ipnMessage['residence_country'] ) ) {
				$message['country'] = $ipnMessage['residence_country'];
			}
		}

		// payment-specific message handling
		if ( $ipnMessage['txn_type'] == 'subscr_payment' ) {

			$message['gateway_txn_id'] = $ipnMessage['txn_id'];
			$message['currency'] = $ipnMessage['mc_currency'];
			$message['gross'] = $ipnMessage['mc_gross'];
			$message['fee'] = $ipnMessage['mc_fee'];
		} else {

			// break the period out for civicrm
			if( isset( $ipnMessage['period3'] ) ) {
				// map paypal period unit to civicrm period units
				$period_map = array(
					'm' => 'month',
					'd' => 'day',
					'w' => 'week',
					'y' => 'year',
				);

				$period = explode( " ", $ipnMessage['period3'] );
				$message['frequency_interval'] = $period[0];
				$message['frequency_unit'] = $period_map[strtolower( $period[1] )];
			}

			if ( isset( $ipnMessage['recur_times'] ) ) {
				$message['installments'] = $ipnMessage['recur_times'];
			} else {
				// forever
				$message['installments'] = 0;
			}

			if ( isset( $ipnMessage['amount3'] ) ) {
				$message['gross'] = $ipnMessage['amount3'];
			} elseif ( isset( $ipnMessage['mc_amount3'] ) ) {
				$message['gross'] = $ipnMessage['mc_amount3'];
			}

			if ( isset( $ipnMessage['mc_currency'] ) ) {
				$message['currency'] = $ipnMessage['mc_currency'];
			}

			if ( isset( $ipnMessage['subscr_date'] ) ) {
				if ( $ipnMessage['txn_type'] == 'subscr_signup' ) {
					$message['create_date'] = strtotime( $ipnMessage['subscr_date'] );
					$message['start_date'] = strtotime( $ipnMessage['subscr_date'] );
				} elseif( $ipnMessage['txn_type'] == 'subscr_cancel' ) {
					$message['cancel_date'] = strtotime( $ipnMessage['subscr_date'] );
				}
				if ( !isset( $message['date'] ) ) {
					$message['date'] = strtotime( $ipnMessage['subscr_date'] );
				}
			}

			if ( $ipnMessage['txn_type'] == 'subscr_modify' ) {
				$message['modified_date'] = $ipnMessage['subscr_effective'];
			}

			if ( $ipnMessage['txn_type'] == 'subscr_failed' ) {

				if ( isset( $ipnMessage['retry_at'] )) {
					$message['failure_retry_date'] = strtotime( $ipnMessage['retry_at'] );
				} elseif( isset( $ipnMessage['failure_retry_date'] )) {
					$message['failure_retry_date'] = strtotime( $ipnMessage['failure_retry_date'] );
				}
			}
		}

		$message['gateway'] = 'paypal';

		if ( !isset( $message['date'] ) ) {
			$message['date'] = time();
		}

	}
}
