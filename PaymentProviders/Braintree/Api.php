<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\Http\OutboundRequest;

class Api {

	/**
	 * @var string
	 */
	protected $merchantId;

	/**
	 * @var string
	 */
	protected $privateKey;

	/**
	 * @var string
	 */
	protected $publicKey;

	/**
	 * @var string
	 */
	protected $endpoint;

	/**
	 * @var string
	 */
	protected $version;

	public function __construct( array $params ) {
		$this->merchantId = $params['merchant-id'];
		$this->privateKey = $params['private-key'];
		$this->publicKey = $params['public-key'];
		$this->endpoint = $params['endpoint'];
		$this->version = $params['version'];
	}

	public function ping(): array {
		return $this->makeQueryApiCall( 'query { ping }' );
	}

	protected function makeQueryApiCall( $query ): array {
		$request = new OutboundRequest( $this->endpoint, 'POST' );
		$request->setHeader( 'Authorization', $this->getAuthorizationHeader() );
		$request->setHeader( 'Braintree-Version', $this->version );
		$request->setHeader( 'Content-type', 'application/json' );
		$request->setBody( json_encode( [ 'query' => $query ] ) );
		return json_decode( $request->execute()['body'], true );
	}

	protected function getAuthorizationHeader() {
		return base64_encode(
			$this->publicKey . ':' . $this->privateKey
		);
	}
}
