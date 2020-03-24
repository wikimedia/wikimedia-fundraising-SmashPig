<?php

namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\CreatePaymentResponse;

class DirectDebitPaymentProvider extends PaymentProvider {

	/**
	 * Create a payment using SEPA direct debit. Currently our API
	 * wrapper only handles recurring payments.
	 * https://docs.adyen.com/payment-methods/sepa-direct-debit/api-only
	 *
	 * @param array $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ) : CreatePaymentResponse {
		$rawResponse = $this->api->createDirectDebitPayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->response ) ) {
			$rawStatus = $rawResponse->response->resultCode ?? null;
			$this->prepareResponseObject(
				$response,
				$rawResponse->response,
				new CreateDirectDebitPaymentStatus(),
				$rawStatus
			);
		} else {
			$responseError = 'response element missing from Adyen createPayment response.';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$responseError,
				LogLevel::ERROR
			) );
			Logger::debug( $responseError, $rawResponse );
		}

		return $response;
	}
}
