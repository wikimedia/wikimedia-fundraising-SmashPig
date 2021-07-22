<?php

namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\StatusNormalizer;
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
	public function createPayment( array $params ): CreatePaymentResponse {
		// probably not the best thing to check on
		if ( !empty( $params['issuer_id'] ) ) {
			$rawResponse = $this->api->createDirectDebitPaymentFromCheckout( $params );
			$response = new CreatePaymentResponse();
			$response->setRawResponse( $rawResponse );
			$rawStatus = $rawResponse['resultCode'];

			$this->mapStatus(
				$response,
				$rawResponse,
				new CreateDirectDebitPaymentStatus(),
				$rawStatus
			);

			if ( $rawStatus === 'RedirectShopper' ) {
				$response->setRedirectUrl( $rawResponse['action']['url'] );
			}
			$this->mapRestIdAndErrors( $response, $rawResponse );
		} else {
			$rawResponse = $this->api->createDirectDebitPayment( $params );
			$response = new CreatePaymentResponse();
			$response->setRawResponse( $rawResponse );

			if ( !empty( $rawResponse->response ) ) {
				$this->mapTxnIdAndErrors(
					$response,
					$rawResponse->response
				);
				$this->mapStatus(
					$response,
					$rawResponse,
					new CreateDirectDebitPaymentStatus(),
					$rawResponse->response->resultCode ?? null
				);
			} else {
				$responseError = 'response element missing from Adyen createPayment response.';
				$response->addErrors( new PaymentError(
					ErrorCode::MISSING_REQUIRED_DATA,
					$responseError,
					LogLevel::ERROR
					)
				);
				Logger::debug( $responseError, $rawResponse );
			}
		}
		return $response;
	}

	protected function getPaymentDetailsStatusNormalizer(): StatusNormalizer {
		return new CreateDirectDebitPaymentStatus();
	}
}
