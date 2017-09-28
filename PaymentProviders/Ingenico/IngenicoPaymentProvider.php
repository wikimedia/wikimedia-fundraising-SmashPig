<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Context;

/**
 * Base class for Ingenico payments. Each payment product group should get
 * a concrete subclass implementing PaymentProvider
 */
abstract class IngenicoPaymentProvider {

	protected $api;
	protected $providerConfiguration;

	public function __construct( $options = [] ) {
		// FIXME: provide objects in constructor
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}

	public function getPaymentStatus( $paymentId ) {
		$path = "payments/$paymentId";
		$response = $this->api->makeApiCall( $path, 'GET' );
		return $response;
	}

	public function approvePayment( $paymentId, $params ) {
		$path = "payments/$paymentId/approve";
		$response = $this->api->makeApiCall( $path, 'POST', $params );
		return $response;
	}

	public function cancelPayment( $paymentId ) {
		$path = "payments/$paymentId/cancel";
		$response = $this->api->makeApiCall( $path, 'POST' );
		return $response;
	}
}
