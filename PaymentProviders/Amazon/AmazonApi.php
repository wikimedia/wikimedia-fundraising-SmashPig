<?php namespace SmashPig\PaymentProviders\Amazon;

use SmashPig\Core\Context;

/**
 * Utility functions for the PayWithAmazon SDK
 */
class AmazonApi {

	public static function createIpnHandler( $headers, $body ) {
		$config = Context::get()->getConfiguration();
		$klass = $config->val( 'ipn-handler-class' );
		return new $klass( $headers, $body );
	}
}
