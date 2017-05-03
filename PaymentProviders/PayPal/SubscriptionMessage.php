<?php

namespace SmashPig\PaymentProviders\PayPal;

class SubscriptionMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {

		$message['recurring'] = '1';

		// Contact info
		if ( $ipnMessage['txn_type'] === 'subscr_signup' || $ipnMessage['txn_type'] === 'subscr_payment' || $ipnMessage['txn_type'] === 'subscr_modify' ) {
			$message['middle_name'] = '';

			if ( isset( $ipnMessage['address_street'] ) ) {
				$split = explode("\n", str_replace("\r", '', $ipnMessage['address_street']));
				$message['street_address'] = $split[0];
				if ( count( $split ) > 1 ) {
					$message['supplemental_address_1'] = $split[1];
				}
			}
		}

		// subtype-specific message handling
		switch( $ipnMessage['txn_type'] ) {
			case 'subscr_signup':
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

				if ( !isset( $message['installments'] ) ) {
					// forever
					$message['installments'] = 0;
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
				break;

			case 'subscr_modify':
				$message['modified_date'] = $ipnMessage['subscr_effective'];
				break;

			case 'subscr_failed':
				if ( isset( $ipnMessage['retry_at'] )) {
					$message['failure_retry_date'] = strtotime( $ipnMessage['retry_at'] );
				} elseif( isset( $ipnMessage['failure_retry_date'] )) {
					$message['failure_retry_date'] = strtotime( $ipnMessage['failure_retry_date'] );
				}
				break;
			default:
		}

		$message['gateway'] = 'paypal';

		if ( !isset( $message['date'] ) ) {
			$message['date'] = time();
		}

	}
}
