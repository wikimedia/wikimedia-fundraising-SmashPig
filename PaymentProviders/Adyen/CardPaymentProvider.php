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
	 * @param array $params
	 *  for a recurring installment, needs
	 *  * 'recurring_payment_token'
	 *  * 'order_id'
	 *  * 'recurring'
	 *  * 'amount'
	 *  * 'currency'
	 * for a payment from encrypted card details, needs
	 *  * 'encrypted_payment_data' with subkeys from Checkout UI
	 *  * 'order_id'
	 *  * 'amount'
	 *  * 'currency'
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		if ( !empty( $params['encrypted_payment_data'] ) ) {
			$rawResponse = $this->api->createPaymentFromEncryptedDetails(
				$params
			);
			$response = new CreatePaymentResponse();
			$response->setRawResponse( $rawResponse );
			$this->mapStatus(
				$response,
				$rawResponse,
				new CreatePaymentStatus(),
				$rawResponse['resultCode']
			);
			// TODO: mapTxnIdAndErrors for REST results
			$response->setGatewayTxnId( $rawResponse['pspReference'] );
		} else {
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
				$response->addErrors(
					new PaymentError(
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
}
