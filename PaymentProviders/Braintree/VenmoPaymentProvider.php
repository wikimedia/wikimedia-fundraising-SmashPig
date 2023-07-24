<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class VenmoPaymentProvider extends PaymentProvider {

	protected function getInvalidParams( array $params ): array {
		$invalidParams = [];
		if ( empty( $params['payment_token'] ) ) {
			$invalidParams[] = 'payment_token';
		}
		if ( empty( $params['order_id'] ) ) {
			$invalidParams[] = 'order_id';
		}
		if ( empty( $params['amount'] ) ) {
			$invalidParams[] = 'amount';
		}
		if ( empty( $params['device_data'] ) ) {
			$invalidParams[] = 'device_data';
		}
		// currency USD is the only supported currency
		if ( empty( $params['currency'] ) || 'USD' !== $params['currency'] ) {
			$invalidParams[] = 'currency';
		}
		return $invalidParams;
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		$invalidParams = $this->getInvalidParams( $params );
		$response = new CreatePaymentResponse();
		// Get ValidationError from transformToApiParams if currency not supported
		if ( count( $invalidParams ) > 0 ) {
			$response->setSuccessful( false );
			$response->setStatus( FinalStatus::FAILED );
			foreach ( $invalidParams as $invalidParam ) {
				$response->addValidationError(
					new ValidationError( $invalidParam,
						null, [],
						'Invalid ' . $invalidParam )
				);
			}
		} else {
			// todo
		}
		return $response;
	}
}
