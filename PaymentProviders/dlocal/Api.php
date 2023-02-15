<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;
use Symfony\Component\HttpFoundation\Response;

class Api {

	/**
	 * @var string
	 */
	public const PAYMENT_METHOD_ID_CARD = 'CARD';

	/**
	 * @var string
	 */
	public const PAYMENT_METHOD_FLOW_DIRECT = 'DIRECT';

	/**
	 * @var string
	 */
	public const PAYMENT_METHOD_FLOW_REDIRECT = 'REDIRECT';

	/**
	 * @var string
	 */
	protected $endpoint;

	/**
	 * @var string
	 */
	protected $login;

	/**
	 * @var string
	 */
	protected $trans_key;

	/**
	 * @var string
	 */
	protected $secret;

	/**
	 * dLocal API Version.
	 *
	 * 2.1 is the current version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * @var SignatureCalculator
	 */
	private $signatureCalculator;

	public function __construct( array $params ) {
		$this->endpoint = $params['endpoint'];
		$this->login = $params['login'];
		$this->trans_key = $params['trans-key'];
		$this->secret = $params['secret'];
		$this->version = $params['version'];
		$this->signatureCalculator = Context::get()->getProviderConfiguration()->object( 'signature-calculator' );
	}

	/**
	 * @param string $method
	 * @param string $route
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function makeApiCall( string $method = 'POST', string $route = '', array $params = [] ): array {
		$request = $this->createRequestBasedOnMethodAndSetBody( $method, $route, $params );

		$this->setRequestHeaders( $request );
		$rawResponse = $request->execute();

		if ( $this->responseHasErrorStatusCode( $rawResponse ) ) {
			throw new ApiException(
				'Response Error(' . $rawResponse['status'] . ') ' . $rawResponse['body']
			);
		}

		return json_decode( $rawResponse['body'], true );
	}

	public function getPaymentMethods( string $country ): array {
		$params = [
			'country' => $country,
		];

		return $this->makeApiCall( 'GET', 'payments-methods', $params );
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function authorizePayment( array $params ): array {
		$apiParams = $this->mapParamsToApiAuthorizeRequestParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $apiParams );
	}

		/**
		 * @param array $params
		 * @return array
		 * @throws ApiException
		 */
	public function redirectPayment( array $params ): array {
		$apiParams = $this->getCreatePaymentApiParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $apiParams );
	}

	/**
	 * Capture authorized payment.
	 *
	 * $params['gateway_txn_id'] is required
	 *
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function capturePayment( array $params ): array {
		$apiParams = $this->mapParamsToApiCaptureRequestParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $apiParams );
	}

	/**
	 * Get payment status.
	 *
	 * https://docs.dlocal.com/reference/retrieve-a-payment-status
	 *
	 * @param string $gatewayTxnId
	 * @return array
	 * @throws ApiException
	 */
	public function getPaymentStatus( string $gatewayTxnId ): array {
		$route = 'payments/' . $gatewayTxnId . '/status';
		return $this->makeApiCall( 'GET', $route );
	}

	/**
	 * @param string $gatewayTxnId
	 *
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancelPayment( string $gatewayTxnId ): array {
		$route = 'payments/' . $gatewayTxnId . '/cancel';
		return $this->makeApiCall( 'POST', $route );
	}

	/**
	 * Set dLocal request headers
	 * https://docs.dlocal.com/reference/payins-security#headers
	 *
	 * @param OutboundRequest $request
	 * @return void
	 */
	protected function setRequestHeaders( OutboundRequest $request ): void {
		// date format is ISO8601 compatible
		$date = ( new \DateTime() )->format( 'Y-m-d\TH:i:s.v\Z' );

		// set the simple headers
		$request->setHeader( 'X-Date', $date )
			->setHeader( 'X-Login', $this->login )
			->setHeader( 'X-Trans-Key', $this->trans_key )
			->setHeader( 'Content-Type', 'application/json' )
			->setHeader( 'X-Version', $this->version )
			->setHeader( 'User-Agent', 'SmashPig' );

		// calculate the request signature and add to 'Authorization' header
		// as instructed in https://docs.dlocal.com/reference/payins-security#headers
		$requestBody = ( $request->getMethod() === 'POST' ) ? $request->getBody() : '';
		$signatureInput = $this->login . $date . $requestBody;
		$signature = $this->signatureCalculator->calculate( $signatureInput, $this->secret );
		// dLocal signatures have a text prefix which needs to be in the header
		$signaturePrefix = 'V2-HMAC-SHA256, Signature: ';
		$request->setHeader( 'Authorization', $signaturePrefix . $signature );
	}

	/**
	 * The OutboundRequest will be created differently depending on the $method(HTTP verb e.g GET/POST/PUT).
	 * In this function, we detect the verb and create the appropriate form of OutboundRequest.
	 *
	 * The $body of the request is also different between methods, for GET we set the $body to null and for
	 * POST, we json_encode() the $body.
	 *
	 *
	 * @param string $method
	 * @param array $params
	 * @return OutboundRequest
	 */
	protected function createRequestBasedOnMethodAndSetBody( string $method, string $route, array $params ): OutboundRequest {
		$apiUrl = empty( $route ) ? $this->endpoint : $this->endpoint . '/' . $route;

		if ( $method === 'GET' ) {
			if ( $params !== [] ) {
				$apiUrl .= '?' . http_build_query( $params );
			}

			$body = null;
		} else {
			$body = json_encode( $params );
		}

		$request = new OutboundRequest( $apiUrl, $method );
		$request->setBody( $body );
		return $request;
	}

	/**
	 * dLocal uses non-200 HTTP headers to indicate response errors
	 *
	 * @see https://docs.dlocal.com/reference/http-errors-payments
	 * @param array $rawResponse
	 * @return bool
	 */
	protected function responseHasErrorStatusCode( array $rawResponse ): bool {
		return $rawResponse['status'] !== Response::HTTP_OK;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	protected function mapParamsToApiCaptureRequestParams( array $params ): array {
		$apiParams = [];
		if ( array_key_exists( 'gateway_txn_id', $params ) ) {
			$apiParams['authorization_id'] = $params['gateway_txn_id'];
		} else {
			throw new \InvalidArgumentException( "gateway_txn_id is a required field" );
		}
		if ( array_key_exists( 'amount', $params ) ) {
			$apiParams['amount'] = $params['amount'];
		}
		if ( array_key_exists( 'currency', $params ) ) {
			$apiParams['currency'] = $params['currency'];
		}
		if ( array_key_exists( 'order_id', $params ) ) {
			$apiParams['order_id'] = $params['order_id'];
		}
		return $apiParams;
	}

	protected function fillNestedArrayFields( array $sourceArray, array &$destinationArray, array $fieldParams ) {
		$array = [];
		foreach ( $fieldParams as $field => $apiParams ) {
			foreach ( $apiParams as $key => $value ) {
				if ( array_key_exists( $value, $sourceArray ) ) {
					$array[$field][$key] = $sourceArray[$value];
				}
			}
			if ( array_key_exists( $field, $destinationArray ) ) {
				$destinationArray[$field] = array_merge( $destinationArray[$field], $array[$field] );
			} elseif ( count( $array[$field] ) > 0 ) {
				$destinationArray[$field] = $array[$field];
			}
		}
	}

	protected function getCreatePaymentApiParams( array $params ): array {
		$apiParams = [
			'amount' => $params['amount'],
			'currency' => $params['currency'],
			'country' => $params['country'],
			'order_id' => $params['order_id'],
			'payment_method_flow' => self::PAYMENT_METHOD_FLOW_REDIRECT,
			'payer' => [
				'name' => $params['first_name'] . ' ' . $params['last_name']
			]
		];

		if ( array_key_exists( 'description', $params ) ) {
			$apiParams['description'] = $params['description'];
		}

		if ( array_key_exists( 'return_url', $params ) ) {
			$apiParams['callback_url'] = $params['return_url'];
		}

		$apiFields = [];
		$apiFields['payer'] = [
			'email' => 'email',
			'document' => 'fiscal_number',
			'user_reference' => 'contact_id',
			'ip' => 'user_ip',
		];

		$apiFields['address'] = [
			'state' => 'state_province',
			'city' => 'city',
			'zip_code' => 'postal_code',
			'street' => 'street_address',
			'number' => 'street_number',
		];

		$this->fillNestedArrayFields( $params, $apiParams, $apiFields );
		return $apiParams;
	}

	/**
	 * @param array $params
	 * Convert the API request body to DLocal Authorize Payment Request standards
	 * @return array
	 */
	protected function mapParamsToApiAuthorizeRequestParams( array $params ): array {
		$apiParams = $this->getCreatePaymentApiParams( $params );

		if ( array_key_exists( 'payment_token', $params ) ) {
			$apiParams['payment_method_id'] = self::PAYMENT_METHOD_ID_CARD;
			$apiParams['payment_method_flow'] = self::PAYMENT_METHOD_FLOW_DIRECT;
			$apiParams['card'] = [
					'token' => $params['payment_token'],
					'capture' => false
			];
		}

		return $apiParams;
	}

}
