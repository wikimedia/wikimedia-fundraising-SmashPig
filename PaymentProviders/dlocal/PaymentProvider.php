<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class PaymentProvider {
	/**
	 * @var Api
	 */
	protected $api;

	/**
	 * @var ProviderConfiguration
	 */
	protected $providerConfiguration;

	public function __construct() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}

	/**
	 * @throws ValidationException
	 */
	protected static function checkFields( $requiredFields, $input ) {
		$invalidFields = [];
		foreach ( $requiredFields as $field ) {
			if ( empty( $input[ $field ] ) ) {
				$invalidFields[$field] = 'required';
			}
		}

		if ( count( $invalidFields ) ) {
			throw new ValidationException( "Invalid input", $invalidFields );
		}
	}

	/**
	 * @param array $missingParams
	 * @param PaymentProviderResponse $paymentResponse
	 * @return void
	 */
	protected function addPaymentResponseValidationErrors(
		array $params, PaymentProviderResponse $paymentResponse
	): void {
		foreach ( $params as $param => $message ) {
			$paymentResponse->addValidationError(
				new ValidationError( $param, null, [], $message )
			);
		}
	}

}
