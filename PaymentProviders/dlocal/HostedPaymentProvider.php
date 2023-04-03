<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class HostedPaymentProvider extends PaymentProvider implements IPaymentProvider {

	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws ApiException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		try {
			$this->validateCreateHostedPaymentParams( $params );
			if ( empty( $params['recurring_payment_token'] ) ) {
				$rawResponse = $this->api->redirectHostedPayment( $params );
			} else {
				// subsequent recurring will contain the token
				$rawResponse = $this->api->createPaymentFromToken( $params );
			}
			return DlocalCreatePaymentResponseFactory::fromRawResponse( $rawResponse );
		} catch ( ValidationException $validationException ) {
			$response = new CreatePaymentResponse();
			self::handleValidationException( $response, $validationException->getData() );
			return $response;
		} catch ( ApiException $apiException ) {
			return DlocalCreatePaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	/**
	 * Check for the following required params
	 * 'amount'
	 * 'order_id'
	 * 'currency'
	 * 'first_name'
	 * 'last_name'
	 * 'email'
	 * 'fiscal_number'
	 * 'country',
	 * @throws ValidationException
	 */
	private function validateCreateHostedPaymentParams( $params ): void {
		$requiredFields = [
			'amount',
			'currency',
			'country',
			'order_id',
			'first_name',
			'last_name',
			'email',
			'fiscal_number',
		];

		self::checkFields( $requiredFields, $params );
	}

}
