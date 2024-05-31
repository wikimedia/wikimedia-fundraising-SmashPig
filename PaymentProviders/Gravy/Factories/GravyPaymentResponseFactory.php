<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

abstract class GravyPaymentResponseFactory {

	abstract protected static function createBasicResponse(): PaymentProviderResponse;

	/**
	 * @param mixed $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromRawResponse( array $normalizedResponse ): PaymentProviderResponse {
		$response = static::createBasicResponse();

		$rawResponse = $normalizedResponse['raw_response'];
		$isSuccessful = $normalizedResponse['is_successful'];

		$response->setRawResponse( $rawResponse );
		$response->setNormalizedResponse( $normalizedResponse );
		$response->setStatus( $normalizedResponse['status'] );
		$response->setSuccessful( $isSuccessful );
		if ( static::isFailedTransaction( $response->getStatus() ) ) {
			static::addPaymentFailureError( $response, $normalizedResponse[ 'message' ] . ':' . $normalizedResponse[ 'description' ], $normalizedResponse[ 'code' ] );
			return $response;
		}
		$response->setRawStatus( $normalizedResponse['raw_status'] );
		static::decorateResponse( $response, $normalizedResponse );
		return $response;
	}

	/**
	 * @param string $status
	 * @return bool
	 */
	protected static function isFailedTransaction( string $status ): bool {
		return $status === FinalStatus::FAILED;
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param string|null $statusDetail
	 * @param string|null $statusCode
	 * @return void
	 */
	protected static function addPaymentFailureError( PaymentProviderResponse $paymentResponse, ?string $statusDetail = 'Unknown error', ?string $errorCode = null ): void {
		$paymentResponse->addErrors(
			new PaymentError(
				$errorCode,
				$statusDetail,
				LogLevel::ERROR
			)
		);
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $rawResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		// Default behavior is to do nothing here, but child classes can override it.
	}
}
