<?php
namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\SmashPigException;

class HostedCheckoutProvider extends IngenicoPaymentProvider {
	/**
	 * @var subdomain
	 */
	protected $subdomain;
	public function __construct( array $options = array() ) {
		parent::__construct( $options );
		if ( array_key_exists( 'subdomain', $options ) ) {
			$this->subdomain = $options['subdomain'];
		} else {
			throw new SmashPigException( "Subdomain key missing from configuration." );
		}
	}

	function createHostedPayment( $params ) {
		$path = 'hostedcheckouts';
		$response = $this->api->makeApiCall( $path, 'POST', $params );
		return $response;
	}

	function getHostedPaymentUrl( $partialRedirectUrl ) {
		return "https://{$this->subdomain}.$partialRedirectUrl";
	}

	function getHostedPaymentStatus( $hostedPaymentId ) {
		$path = "hostedcheckouts/$hostedPaymentId";
		$response = $this->api->makeApiCall( $path, 'GET' );
		return $response;
	}
}
