<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;

class Api {

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
		$this->trans_key = $params['trans_key'];
		$this->secret = $params['secret'];
		$this->version = $params['version'];
		$this->signatureCalculator = Context::get()->getProviderConfiguration()->object( 'signature-calculator' );
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function makeApiCall( array $params ): array {
		$request = $this->getNewRequest();
		$request->setBody( json_encode( $params, JSON_THROW_ON_ERROR ) );
		$this->setRequestHeaders( $request );
		return $request->execute();
	}

	/**
	 * Set dLocal request headers
	 * https://docs.dlocal.com/reference/payins-security#headers
	 *
	 * @param OutboundRequest $request
	 * @return void
	 */
	protected function setRequestHeaders( OutboundRequest $request ): void {
		// set date header using ATOM format which is ISO8601 compatible
		$date = ( new \DateTime() )->format( \DateTime::ATOM );

		// set the simple headers
		$request->setHeader( 'X-Date', $date )
			->setHeader( 'X-Login', $this->login )
			->setHeader( 'X-Trans-Key', $this->trans_key )
			->setHeader( 'Content-Type', 'application/json' )
			->setHeader( 'X-Version', $this->version )
			->setHeader( 'User-Agent', 'SmashPig' );

		// calculate the request signature and add to 'Authorization' header
		// as instructed in https://docs.dlocal.com/reference/payins-security#headers
		$signatureInput = $this->login . $date . $request->getBody();
		$signature = $this->signatureCalculator->calculate( $signatureInput, $this->secret );
		$request->setHeader( 'Authorization', $signature );
	}

	/**
	 * @return OutboundRequest
	 */
	protected function getNewRequest(): OutboundRequest {
		return new OutboundRequest( $this->endpoint );
	}

}
