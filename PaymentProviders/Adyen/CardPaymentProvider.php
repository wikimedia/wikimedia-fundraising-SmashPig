<?php

namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\CreatePaymentResponse;

class CardPaymentProvider extends PaymentProvider {

	/**
	 * @param $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( $params ) {
		$rawResponse = $this->api->createPayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->paymentResult ) ) {
			$rawStatus = $rawResponse->paymentResult->resultCode ?? null;
			$this->prepareResponseObject(
				$response,
				$rawResponse->paymentResult,
				new CreatePaymentStatus(),
				$rawStatus
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
