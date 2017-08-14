<?php namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Context;

/**
 * abstract static inheritance? Whatamidoing?
 */
abstract class Message {
	public static function fromIpnMessage( $ipnArray ) {
		$config = Context::get()->getProviderConfiguration();

		$message = array();
		$map = $config->val( 'var_map' );
		foreach ( $map as $rx => $tx ) {
			if ( array_key_exists( $rx, $ipnArray ) ) {
				$message[$tx] = $ipnArray[$rx];
			}
		}

		if ( isset( $message['contribution_tracking_id'] ) ) {
			$message['order_id'] = $message['contribution_tracking_id'];
		}

		// If someone's PayPal account is set to their name we don't want
		// it to go in the address box. They should put in a business name
		// or something.
		if ( isset( $message['supplemental_address_1'] )
			&& $message['supplemental_address_1'] ===
			"{$message['first_name']} {$message['last_name']}" ) {
			unset( $message['supplemental_address_1'] );
		}

		// TODO: once recurring messages are normalized with var_map,
		// always do the strtotime
		if ( isset( $message['date'] ) ) {
			$message['date'] = strtotime( $message['date'] );
		}

		static::normalizeMessage( $message, $ipnArray );
		return $message;
	}

	static function normalizeMessage( &$message, $ipnArray ) {
	}
}
