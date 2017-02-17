<?php

namespace SmashPig\Core\Http;

use HttpRuntimeException;
use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

class CurlWrapper {

	/**
	 * @var Configuration
	 */
	protected $configuration;

	public function __construct() {
		$this->configuration = Context::get()->getConfiguration();
	}

	public function execute( $url, $method, $responseHeaders, $data ) {
		if ( is_array( $data ) ) {
			$data = http_build_query( $data );
		}

		Logger::info( "Initiating cURL" );
		$ch = curl_init();

		// Always capture the cURL output
		$curlDebugLog = fopen( 'php://temp', 'r+' );

		$curlOptions = $this->getCurlOptions( $url, $method, $responseHeaders, $data, $curlDebugLog );
		curl_setopt_array( $ch, $curlOptions );

		// TODO: log timing
		$loopCount = $this->configuration->val( 'curl/retries' );
		$tries = 0;
		$parsed = null;
		do {
			Logger::info(
				"Preparing to send {$method} request to {$url}"
			);

			// Execute the cURL operation
			$response = curl_exec( $ch );

			// Always read the verbose output
			rewind( $curlDebugLog );
			$logged = fread( $curlDebugLog, 8192 );

			if ( $response !== false ) {
				// The cURL operation was at least successful, what happened in it?
				Logger::debug( "cURL verbose logging: $logged" );

				$curlInfo = curl_getinfo( $ch );
				$parsed = $this->parseResponse( $response, $curlInfo );

				/**
				 * @var ResponseValidator
				 */
				$validator = $this->configuration->object( 'curl/validator' );
				$continue = $validator->shouldRetry( $parsed );

			} else {
				// Well the cURL transaction failed for some reason or another. Try again!
				$continue = true;

				$errno = curl_errno( $ch );
				$err = curl_error( $ch );

				Logger::alert(
					"cURL transaction to {$url} failed: ($errno) $err.  " .
					"cURL verbose logging: $logged"
				);
			}
			$tries++;
			if ( $tries >= $loopCount ) {
				$continue = false;
			}
		} while ( $continue ); // End while cURL transaction hasn't returned something useful

		// Clean up and return
		curl_close( $ch );
		fclose( $curlDebugLog );

		if ( $response === false ) {
			// no valid response after multiple tries
			throw new HttpRuntimeException(
				"{$method} request to {$url} failed $loopCount times."
			);
		}

		return $parsed;
	}

	protected function getCurlOptions( $url, $method, $headers, $data, $logStream ) {
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => $this->configuration->val( 'curl/user-agent' ),
			CURLOPT_HEADER => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_TIMEOUT => $this->configuration->val( 'curl/timeout' ),
			CURLOPT_FOLLOWLOCATION => 0,
			CURLOPT_SSL_VERIFYPEER => 1,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FORBID_REUSE => true,
			CURLOPT_VERBOSE => true,
			CURLOPT_STDERR => $logStream,
		);
		switch( $method ) {
			case 'PUT':
				$options[CURLOPT_PUT] = 1;
				break;
			case 'DELETE':
			case 'HEAD':
				$options[CURLOPT_CUSTOMREQUEST] = $method;
				break;
			case 'POST':
				$options[CURLOPT_POST] = 1;
				break;
			default:
				break;
		}
		if ( $data !== null ) {
			$options[CURLOPT_POSTFIELDS] = $data;
		}
		foreach ( $headers as $name => $value ) {
			$options[CURLOPT_HTTPHEADER][] = "$name: $value";
		}
		return $options;
	}

	public static function parseResponse( $response, $curlInfo ) {
		$parts = explode( "\r\n\r\n", $response, 2 );
		$headerLines = explode( "\r\n", $parts[0] );
		$responseHeaders = array();
		foreach( $headerLines as $line ) {
			if ( strstr( $line, ': ' ) !== false ) {
				list( $name, $value ) = explode( ': ', $line, 2 );
				$responseHeaders[$name] = $value;
			}
		}
		return array(
			'body' => $parts[1],
			'headers' => $responseHeaders,
			'status' => (int)$curlInfo['http_code']
		);
	}
}