<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

/**
 * This class provides input validation for Card payment requests.
 */
class CardPaymentProviderValidator extends PaymentProviderValidator {

	/**
	 * Checks the one time card create payment input parameters for correctness and completeness.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateOneTimeCreatePaymentInput( array $params ): void {
		parent::validateOneTimeCreatePaymentInput( $params );

		$requiredFields = [
			'gateway_session_id',
			'email',
			'first_name',
			'last_name',
		];

		$required = array_merge(
			$requiredFields,
			$this->addCountrySpecificRequiredFields( $params )
		);

		$this->validateFields( $required, $params );
	}

}
