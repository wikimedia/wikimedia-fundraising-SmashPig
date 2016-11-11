<?php namespace SmashPig\PaymentProviders\PayPal;

use RuntimeException;
use SmashPig\Core\Configuration;
use SmashPig\Core\Logging\Logger;

class PayPalPaymentsAPI {

	// Simply a function to override in testing.
	protected function curl( $ch, $post_fields ) {
		$post_fields['cmd'] = '_notify-validate';
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields );
		return curl_exec( $ch );
	}

	/**
	 * @param array $post_fields Associative array of fields posted to listener
	 * @return bool
	 */
	function validate( $post_fields = array() ) {
		$url = Configuration::getDefaultConfig()->val( 'postback-url' );
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		// TODO we can put VERIFIED in config and generalize this

		// Always capture the cURL output
		$curlDebugLog = fopen( 'php://temp', 'r+' );
		curl_setopt( $ch, CURLOPT_VERBOSE, true );
		curl_setopt( $ch, CURLOPT_STDERR, $curlDebugLog );

		$response = $this->curl( $ch, $post_fields );

		// Read the logging output
		rewind( $curlDebugLog );
		$logged = fread( $curlDebugLog, 8192 );
		fclose( $curlDebugLog );
		Logger::debug( "cURL verbose logging: $logged" );

		if ( $response === 'VERIFIED' ) {
			return true;
		} elseif ( $response === 'INVALID' ) {
			return false;
		} else {
			// TODO: Log txn_id. This is annoying because of the random document formats.
			Logger::debug(
				"Unknown response from PayPal IPN PB: [{$response}].\n" .
				"Verbose logging: $logged"
			);
			// FIXME: The same thing happens for "INVALID" and totally broken
			// responses. Differentiate.
			return false;
		}
	}
}
