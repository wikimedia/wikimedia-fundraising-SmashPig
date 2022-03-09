<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\Context;
use SmashPig\PaymentProviders\ApprovePaymentResponse;
use SmashPig\PaymentProviders\CreatePaymentResponse;
use SmashPig\PaymentProviders\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\IPaymentProvider;

class PaymentProvider implements IPaymentProvider {

	/**
	 * @var Api
	 */
	protected $api;

	public function __construct() {
		$config = Context::get()->getProviderConfiguration();
		$this->api = $config->object( 'api' );
	}

	/**
	 * @return CreatePaymentSessionResponse
	 */
	public function createPaymentSession(): CreatePaymentSessionResponse {
		$rawResponse = $this->api->createClientToken();
		$response = new CreatePaymentSessionResponse();
		$response->setRawResponse( $rawResponse );
		$response->setPaymentSession( $rawResponse['data']['createClientToken']['clientToken'] );
		return $response;
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}
}
