<?php

namespace SmashPig\PaymentProviders\Ingenico;

use DateTime;
use DateTimeZone;
use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;
use SmashPig\Core\ApiException;
use SmashPig\Core\Logging\Logger;
use Symfony\Component\HttpFoundation\Response;

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

	/**
	 * Api constructor.
	 *
	 * @param $baseUrl
	 * @param $merchantId
	 *
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
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

	/**
	 * @param string $path
	 * @param string $method
	 * @param array|null $data
	 *
	 * @return array|null
	 * @throws \SmashPig\Core\ApiException
	 */
	public function makeApiCall( $path, $method = 'POST', $data = null ) {
		if ( is_array( $data ) ) {
			// FIXME: this is weird, maybe OutboundRequest should handle this part
			if ( $method === 'GET' ) {
				$path .= '?' . http_build_query( $data );
				$data = null;
			} else {
				$originalData = $data;
				$data = json_encode( $data );
				// additional logging to catch any json_encode failures.
				if ( $data === false ) {
					$jsonError = json_last_error_msg();
					Logger::debug(
						"Unable to json_encode() request params. (" . $jsonError . ") (data: " . print_r( $originalData, true ) . ")",
						$originalData
					);
				}
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
		$decodedResponseBody = json_decode( $response['body'], true );
		$expectedEmptyBody = ( $response['status'] === Response::HTTP_NO_CONTENT );

		if ( !( $expectedEmptyBody && empty( $decodedResponseBody ) ) ) {
			if ( $this->responseBodyHasError( $decodedResponseBody ) ) {
				$this->throwApiException( $response, $decodedResponseBody );
			}
		}

		return $decodedResponseBody;
	}

	/**
	 * @param array $decodedResponseBody
	 *
	 * @return bool
	 */
	protected function responseBodyHasError( $decodedResponseBody ) {
		if ( !isset( $decodedResponseBody ) ) {
			return true;
		} elseif ( !empty( $decodedResponseBody['errorId'] )
			&& !empty( $decodedResponseBody['errors'] ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param $response
	 * @param $decodedResponse
	 *
	 * @throws \SmashPig\Core\ApiException
	 */
	protected function throwApiException( $response, $decodedResponse ) {
		$ex = new ApiException();
		if ( $decodedResponse === null ) {
			$message = "Response body is not valid JSON: '{$response['body']}'";
		} else {
			$message = $this->getApiExceptionMessage(
				$decodedResponse['errorId'],
				$decodedResponse['errors']
			);
			$ex->setRawErrors( $decodedResponse['errors'] );
		}

		$ex->setMessage( $message );
		throw $ex;
	}

	/**
	 * @param $errorId
	 * @param $errors
	 *
	 * @return bool|string
	 */
	protected function getApiExceptionMessage( $errorId, $errors ) {
		$message = "Ingenico error id {$errorId} : ";
		foreach ( $errors as $error ) {
			$message .= "Error code {$error['code']}: {$error['message']}. ";
		}
		return substr( $message, 0, -1 );
	}

}
