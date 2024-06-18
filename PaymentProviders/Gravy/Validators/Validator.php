<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

class Validator {

	/**
	 * @throws ValidationException
	 */
	public function createPaymentInputIsValid( array $params ): bool {
		$required = [
			'gateway_session_id',
			'amount',
			'currency'
		];

		$this->checkFields( $required, $params );
		return true;
	}

	/**
	 * @throws ValidationException
	 */
	protected function checkFields( array $requiredFields, array $params ) {
		$invalidFields = [];
		foreach ( $requiredFields as $field ) {
			if ( empty( $params[$field] ) ) {
				$invalidFields[$field] = 'required';
			}
		}

		if ( count( $invalidFields ) ) {
			throw new ValidationException( "Invalid input", $invalidFields );
		}
	}

}
