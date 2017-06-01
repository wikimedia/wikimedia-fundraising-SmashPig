<?php namespace SmashPig\PaymentProviders\PayPal;

use RuntimeException;
use SmashPig\Core\Context;
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
	public function validate( $post_fields = array() ) {

		// https://www.paypal-knowledge.com/infocenter/index?page=content&id=FAQ1336&actp=LIST
		// PayPal randomly fails to validate messages, so try a few times.
		$max_attempts = 7;

		for ( $i = 0; $i < $max_attempts; $i++ ) {
			$url = Context::get()->getConfiguration()->val( 'postback-url' );
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
			}
			// Must be an HTML page, keep trying.
		}

		throw new RuntimeException( 'Failed to validate message after ' .
			$max_attempts . ' attempts: ' . print_r( $post_fields, true ) );
	}

}
