<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

class Validator {

	/**
	 * @throws ValidationException
	 */
	public function checkDonorInput( array $params ): void {
		$required = [
			'email',
		];

		$this->checkFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function checkCreatePaymentInput( array $params ): void {
		$required = [
			'gateway_session_id',
			'amount',
			'currency',
			'order_id',
			'email',
			'first_name',
			'last_name'
		];

		$this->checkFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function checkApprovePaymentInput( array $params ): void {
		$required = [
			'gateway_txn_id',
			'amount'
		];

		$this->checkFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function checkCreateDonorInput( array $params ): void {
		$required = [
			'first_name',
			'last_name',
			'email'
		];

		$this->checkFields( $required, $params );
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
