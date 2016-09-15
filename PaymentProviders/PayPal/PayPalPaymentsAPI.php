<?php namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Configuration;

class PayPalPaymentsAPI {

	// Simply a function to override in testing.
	protected function curl ( $ch, $post_fields ) {
		$post_fields['cmd'] = '_notify-validate';
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields );
		return curl_exec( $ch );
	}

	/**
	 * @return boolean
	 */
	function validate( $post_fields ) {
		$url = Configuration::getDefaultConfig()
			->val( 'endpoints/listener/postback-url' );
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		// TODO we can put VERIFIED in config and generalize this
		return $this->curl( $ch, $post_fields ) === 'VERIFIED';
	}
}
