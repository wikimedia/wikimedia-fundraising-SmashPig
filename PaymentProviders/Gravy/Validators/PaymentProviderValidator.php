<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

abstract class PaymentProviderValidator {
	use ValidatorTrait;

	/**
	 * For some countries, additional fields are required so we check
	 * the country code and add the required fields accordingly to our
	 * validation checks.
	 */
	private const FIELD_COUNTRY_REQUIREMENTS = [
		'fiscal_number' => [
			'AR', 'BR', 'CL', 'CO', 'ID', 'IN', 'JP', 'MX', 'MY', 'PH', 'TH', 'ZA',
		],
	];

	/**
	 * @throws ValidationException
	 */
	abstract public function validateOneTimeCreatePaymentInput( array $params ): void;

	public function validateCreatePaymentInput( array $params ): void {
		// recurring charge is same across all methods
		if ( isset( $params['recurring_payment_token'] ) ) {
			$this->validateRecurringCreatePaymentInput( $params );
		} else {
			$this->validateOneTimeCreatePaymentInput( $params );
		}
	}

	/**
	 * @throws ValidationException
	 * Useful for PayPal transaction flow
	 */
	public function validateDonorInput( array $params ): void {
		$required = [
			'email',
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateRecurringCreatePaymentInput( array $params ): void {
		$defaultRequiredFields = [
			'recurring_payment_token',
			'amount',
			'currency',
			'country',
			'order_id',
			'email',
			'first_name',
			'last_name'
		];

		$required = array_merge(
			$defaultRequiredFields,
			$this->addCountrySpecificRequiredFields( $params )
		);

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateGetLatestPaymentStatusInput( array $params ): void {
		$required = [
			'gateway_txn_id'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateGetRefundInput( array $params ): void {
		$required = [
			'gateway_refund_id',
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateGetReportExecutionInput( array $params ): void {
		$required = [
			'report_execution_id'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateGenerateReportUrlInput( array $params ): void {
		$required = [
			'report_execution_id',
			'report_id'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateRefundInput( array $params ): void {
		$required = [
			'gateway_txn_id',
		];

		if ( isset( $params['amount'] ) && !empty( $params['amount'] ) ) {
			$required[] = 'currency';
		}

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateApprovePaymentInput( array $params ): void {
		$required = [
			'gateway_txn_id',
			'currency',
			'amount'
		];

		$this->validateFields( $required, $params );
	}

	public function validateDeletePaymentTokenInput( array $params ) {
		$required = [
			'recurring_payment_token'
		];

		$this->validateFields( $required, $params );
		return true;
	}

	/**
	 * @throws ValidationException
	 * Useful for PayPal transaction flow
	 */
	public function validateCreateDonorInput( array $params ): void {
		$required = [
			'first_name',
			'last_name',
			'email'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Adds country-specific required fields based on the country code.
	 *
	 * @param array $params
	 * @return string[]
	 */
	protected function addCountrySpecificRequiredFields( array $params ): array {
		$countrySpecificFields = [];
		if ( isset( $params['country'] ) ) {
			$country = $params['country'];
			foreach ( self::FIELD_COUNTRY_REQUIREMENTS as $field => $countries ) {
				if ( in_array( $country, $countries, true ) ) {
					$countrySpecificFields[] = $field;
				}
			}
		}
		return $countrySpecificFields;
	}
}
