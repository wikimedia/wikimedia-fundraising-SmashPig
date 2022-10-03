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
	 * @param string $endpoint
	 * @param string $user
	 * @param string $password
	 * @param string $certificate_path
	 * @param string $version
	 */
	public function __construct( string $endpoint, string $user, string $password, string $certificate_path, string $version ) {
		$this->endpoint = $endpoint;
		$this->user = $user;
		$this->password = $password;
		$this->certificate_path = $certificate_path;
		$this->version = $version;
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
