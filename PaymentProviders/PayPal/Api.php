<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Http\OutboundRequest;

class Api {

	/**
	 * @var string API Endpoint.
	 */
	protected $endpoint;

	/**
	 * @var string Paypal's API USER param.
	 */
	protected $user;

	/**
	 * @var string Paypal's API PWD param.
	 */
	protected $password;

	/**
	 * @var string Path to API Certificate file.
	 */
	protected $certificate_path;

	/**
	 * @var string Paypal's VERSION param.
	 */
	protected $version;

	/**
	 * @param array $params required keys 'endpoint', 'user', 'password', 'certificate_path', and 'version'
	 */
	public function __construct( array $params ) {
		$this->endpoint = $params[ 'endpoint' ];
		$this->user = $params[ 'user' ];
		$this->password = $params[ 'password' ];
		$this->certificate_path = $params[ 'certificate_path' ];
		$this->version = $params[ 'version' ];
	}

	/**
	 * Base-level API call method. All calls should come through here.
	 *
	 * @param array $params
	 * @return array
	 */
	public function makeApiCall( array $params ) {
		$requestParams = array_merge( $this->getDefaultRequestParams(), $params );
		$request = new OutboundRequest( $this->endpoint, 'POST' );
		$request->setCertPath( $this->certificate_path );
		$request->setBody( http_build_query( $requestParams ) );
		$response = $request->execute();
		parse_str( $response['body'], $result );
		return $result;
	}

	/**
	 * Paypal expects auth and version params to be sent within the request body.
	 * https://developer.paypal.com/api/nvp-soap/gs-PayPalAPIs/#link-callpayload
	 *
	 * Note: We're using Certificate Auth and not Signature Auth so that's
	 * why SIGNATURE is missing. I couldn't find an example for Certificate
	 * auth on that page.
	 *
	 * @return array
	 */
	private function getDefaultRequestParams(): array {
		$params['USER'] = $this->user;
		$params['PWD'] = $this->password;
		$params['VERSION'] = $this->version;
		return $params;
	}

}