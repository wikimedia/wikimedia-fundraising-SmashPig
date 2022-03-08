<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\Context;
use SmashPig\PaymentProviders\ApprovePaymentResponse;
use SmashPig\PaymentProviders\CreatePaymentResponse;
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

	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}
}
