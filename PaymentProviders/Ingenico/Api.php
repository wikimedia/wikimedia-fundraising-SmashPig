<?php

namespace SmashPig\PaymentProviders\Ingenico;

use DateTime;
use DateTimeZone;
use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;

/**
 * Prepares and sends requests to the Ingenico Connect API.
 */
class Api {

	const API_VERSION = 'v1';

	/**
	 * @var Authenticator
	 */
	protected $authenticator;
	protected $baseUrl;
	protected $merchantId;

	public function __construct( $baseUrl, $merchantId ) {
		// Ensure trailing slash
		if ( substr( $baseUrl, -1 ) !== '/' ) {
			$baseUrl .= '/';
		}
		$this->baseUrl = $baseUrl;
		$this->merchantId = $merchantId;
		// FIXME: provide objects in constructor
		$config = Context::get()->getProviderConfiguration();
		$this->authenticator = $config->object( 'authenticator' );
	}

	public function makeApiCall( $path, $method = 'POST', $data = null ) {
		if ( is_array( $data ) ) {
			// FIXME: this is weird, maybe OutboundRequest should handle this part
			if ( $method === 'GET' ) {
				$path .= '?' . http_build_query( $data );
				$data = null;
			} else {
				$data = json_encode( $data );
			}
		}
		$url = $this->baseUrl . self::API_VERSION . "/{$this->merchantId}/$path";
		$request = new OutboundRequest( $url, $method );
		$request->setBody( $data );
		if ( $method !== 'GET' ) {
			$request->setHeader( 'Content-Type', 'application/json' );
		}
		// Set date header manually so we can use it in signature generation
		$date = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$request->setHeader( 'Date', $date->format( 'D, d M Y H:i:s T' ) );

		// set more headers...

		$this->authenticator->signRequest( $request );

		$response = $request->execute();

		$decoded = json_decode( $response['body'], true );
		$this->checkErrors( $response, $decoded );

		return $decoded;
	}

	protected function error_search( $array ) {
		if ( !empty( $array['errors'] ) ) {
			return $array['errors'];
		}
		if ( array_key_exists( 'payment', $array ) && array_key_exists( 'errors', $array['payment']['statusOutput'] ) ) {
			return $array['payment']['statusOutput']['errors'];
		} elseif ( array_key_exists( 'createdPaymentOutput', $array ) && array_key_exists( 'errors', $array['createdPaymentOutput']['payment']['statusOutput'] ) ) {
			return $array['createdPaymentOutput']['payment']['statusOutput']['errors'];
		} else {
			return [];
		}
	}

	/**
	 * @param array $response The CurlWrapper-formatteed response from Ingenico
	 * @param array $decoded The decoded JSON response body. Null if bad JSON
	 * @throws ApiException
	 */
	protected function checkErrors( $response, $decoded ) {
		if ( $decoded === null ) {
			throw new ApiException( "Response body is not valid JSON: '{$response['body']}'" );
		}

		$errors = $this->error_search( $decoded );

		if ( !empty( $errors ) ) {
			$messages = [];
			if ( !empty( $decoded['errorId'] ) ) {
				$messages[] = "Ingenico error id {$decoded['errorId']}.";
			}
			foreach ( $errors as $error ) {
				$messages[] = "Error code {$error['code']}: {$error['message']}.";
			}
			$concatenated = implode( ' ', $messages );
			$ex = new ApiException( $concatenated );
			$ex->setRawErrors( $errors );
			throw $ex;
		}
	}
}
