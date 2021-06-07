<?php

namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\ApprovePaymentResponse;
use SmashPig\PaymentProviders\CancelPaymentResponse;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentDetailResponse;
use SmashPig\PaymentProviders\PaymentMethodResponse;
use SmashPig\PaymentProviders\PaymentProviderResponse;

/**
 * Class PaymentProvider
 * @package SmashPig\PaymentProviders\Adyen
 *
 *
 */
abstract class PaymentProvider implements IPaymentProvider {
	/**
	 * @var Api
	 */
	protected $api;

	/**
	 * @var \SmashPig\Core\ProviderConfiguration
	 */
	protected $providerConfiguration;

	public function __construct() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}

	/**
	 * Gets available payment methods
	 *
	 * @param array $params
	 * @return PaymentProviderResponse
	 */
	public function getPaymentMethods( array $params ) {
		// TODo: cache
		$rawResponse = $this->api->getPaymentMethods( $params );

		$response = new PaymentMethodResponse();
		$response->setRawResponse( $rawResponse );

		return $response;
	}

	/**
	 * Get more payment details from the redirect result
	 *
	 * @param string $redirectResult
	 * @return PaymentDetailResponse
	 */
	public function getHostedPaymentDetails( $redirectResult ) {
		$rawResponse = $this->api->getPaymentDetails( $redirectResult );

		$response = new PaymentDetailResponse();
		$response->setRawResponse( $rawResponse );

		return $response;
	}

	/**
	 * Approves a payment
	 * FIXME: Should probably put this on a separate interface from IPaymentProvider.
	 * Leaving this on the base class for now since subclasses need
	 * an implementation and DirectDebit doesn't have one.
	 *
	 * @param array $params
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		$rawResponse = $this->api->approvePayment( $params );
		$response = new ApprovePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->captureResult ) ) {
			$this->mapTxnIdAndErrors(
				$response,
				$rawResponse->captureResult
			);
			$this->mapStatus(
				$response,
				$rawResponse,
				new ApprovePaymentStatus(),
				$rawResponse->captureResult->response ?? null
			);
		} else {
			$responseError = 'captureResult element missing from Adyen approvePayment response.';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$responseError,
				LogLevel::ERROR
			) );
			Logger::debug( $responseError, $rawResponse );
		}

		return $response;
	}

	/**
	 * Cancels a payment
	 *
	 * @param string $gatewayTxnId
	 * @return CancelPaymentResponse
	 */
	public function cancelPayment( $gatewayTxnId ) {
		$rawResponse = $this->api->cancel( $gatewayTxnId );
		$response = new CancelPaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->cancelResult ) ) {
			$this->mapTxnIdAndErrors(
				$response,
				$rawResponse->cancelResult,
				false
			);
			$this->mapStatus(
				$response,
				$rawResponse,
				new CancelPaymentStatus(),
				$rawResponse->cancelResult->response ?? null
			);
		} else {
			$responseError = 'cancelResult element missing from Adyen cancel response.';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$responseError,
				LogLevel::ERROR
			) );
			Logger::debug( $responseError, $rawResponse );
		}

		return $response;
	}

	/**
	 * Maps gateway transaction ID and errors from $rawResponse to $response. The replies we get back from the
	 * Adyen API have a section with 'pspReference' and 'refusalReason' properties. Exactly where this section
	 * is depends on the API call, but we map them all the same way.
	 *
	 * @param PaymentProviderResponse $response An instance of a PaymentProviderResponse subclass to be populated
	 * @param object $rawResponse The bit of the API response that has pspReference and refusalReason
	 * @param bool $checkForRetry Whether to test the refusalReason against a list of retryable reasons.
	 */
	protected function mapTxnIdAndErrors(
		PaymentProviderResponse $response,
		$rawResponse,
		$checkForRetry = true
	) {
		// map trxn id
		if ( !empty( $rawResponse->pspReference ) ) {
			$response->setGatewayTxnId( $rawResponse->pspReference );
		} else {
			$message = 'Unable to map Adyen Gateway Transaction ID';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_TRANSACTION_ID,
				$message,
				LogLevel::ERROR
			) );
			Logger::debug( $message, $rawResponse );
		}
		// map errors
		if ( !empty( $rawResponse->refusalReason ) ) {
			if ( $checkForRetry ) {
				if ( $this->canRetryRefusalReason( $rawResponse->refusalReason ) ) {
					$errorCode = ErrorCode::DECLINED;
				} else {
					$errorCode = ErrorCode::DECLINED_DO_NOT_RETRY;
				}
			} else {
				$errorCode = ErrorCode::UNEXPECTED_VALUE;
			}
			$response->addErrors( new PaymentError(
				$errorCode,
				$rawResponse->refusalReason,
				LogLevel::INFO
			) );
		}
	}

	/**
	 * Normalize the raw status or add appropriate errors to our response object. We have a group of classes
	 * whose function is normalizing raw status codes for specific API calls. We expect SOME status code back
	 * from any API call, so when that is missing we always add a MISSING_REQUIRED_DATA error. Otherwise we
	 * call the mapper and set the appropriate status on our PaymentProviderResponse object. Errors in
	 * normalization result in adding an UNEXPECTED_VALUE error to the PaymentProviderResponse.
	 *
	 * @param PaymentProviderResponse $response An instance of a PaymentProviderResponse subclass to be populated
	 * @param object $rawResponse The raw API response object, used to log errors.
	 * @param StatusNormalizer $statusMapper An instance of the appropriate status mapper class
	 * @param string $rawStatus The status string from the API response, either from 'resultCode' or 'response'
	 */
	protected function mapStatus(
		PaymentProviderResponse $response,
		$rawResponse,
		StatusNormalizer $statusMapper,
		$rawStatus
	) {
		if ( !empty( $rawStatus ) ) {
			$response->setRawStatus( $rawStatus );
			try {
				$status = $statusMapper->normalizeStatus( $rawStatus );
				$response->setStatus( $status );
			} catch ( \Exception $ex ) {
				$response->addErrors( new PaymentError(
					ErrorCode::UNEXPECTED_VALUE,
					$ex->getMessage(),
					LogLevel::ERROR
				) );
				Logger::debug( 'Unable to map Adyen status', $rawResponse );
			}
		} else {
			$message = 'Missing Adyen status';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$message,
				LogLevel::ERROR
			) );
			Logger::debug( $message, $rawResponse );
		}
	}

	/**
	 * Documented at
	 * https://docs.adyen.com/development-resources/refusal-reasons
	 *
	 * @param string $refusalReason
	 * @return bool
	 */
	private function canRetryRefusalReason( $refusalReason ) {
		// They may prefix the refusal reason with a numeric code
		$trimmedReason = preg_replace( '/^[0-9:]+ /', '', $refusalReason );
		$noRetryReasons = [
			'Acquirer Fraud',
			'Blocked Card',
			'FRAUD',
			'FRAUD-CANCELLED',
			'Invalid Amount',
			'Invalid Card Number',
			'Invalid Pin',
			'No Contract Found',
			'Pin validation not possible',
			'Referral',
			'Restricted Card',
			'Revocation Of Auth',
			'Issuer Suspected Fraud',
		];
		if ( in_array( $trimmedReason, $noRetryReasons ) ) {
			return false;
		}
		return true;
	}
}
