<?php

namespace SmashPig\PaymentProviders\dlocal;

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

	public function __construct( array $params ) {
		$this->endpoint = $params['endpoint'];
		$this->login = $params['login'];
		$this->trans_key = $params['trans_key'];
		$this->secret = $params['secret'];
		$this->version = $params['version'];
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function makeApiCall( array $params ): array {
		$request = $this->getNewRequest();
		$this->setRequestHeaders( $request );
		$request->setBody( $params );
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
		$request->setHeader( 'X-Date', $date )
			->setHeader( 'X-Login', $this->login )
			->setHeader( 'X-Trans-Key', $this->trans_key )
			->setHeader( 'Content-Type', 'application/json' )
			->setHeader( 'X-Version', $this->version )
			->setHeader( 'User-Agent', 'SmashPig' )
			->setHeader( 'Authorization', '' );
	}

	/**
	 * @return OutboundRequest
	 */
	protected function getNewRequest(): OutboundRequest {
		return new OutboundRequest( $this->endpoint );
	}

}
