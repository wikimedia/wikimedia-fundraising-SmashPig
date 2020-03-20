<?php

namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\CreatePaymentResponse;

class CardPaymentProvider extends PaymentProvider {

	/**
	 * Request authorization of a credit card payment
	 *
	 * @param array $params needs 'recurring_payment_token', 'order_id', 'recurring', 'amount', and 'currency'
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ) : CreatePaymentResponse {
		$rawResponse = $this->api->createPayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->paymentResult ) ) {
			$this->mapTxnIdAndErrors(
				$response,
				$rawResponse->paymentResult
			);
			$this->mapStatus(
				$response,
				$rawResponse,
				new CreatePaymentStatus(),
				$rawResponse->paymentResult->resultCode ?? null
			);
		} else {
			$responseError = 'paymentResult element missing from Adyen createPayment response.';
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
