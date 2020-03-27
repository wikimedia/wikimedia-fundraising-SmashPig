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
use SmashPig\PaymentProviders\CreatePaymentResponse;
use SmashPig\PaymentProviders\IPaymentProvider;

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
	 * Approves a payment
	 * FIXME: Should probably put this on a separate interface from IPaymentProvider.
	 * Leaving this on the base class for now since subclasses need
	 * an implementation and DirectDebit doesn't have one.
	 *
	 * @param $params
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( array $params ) : ApprovePaymentResponse {
		$rawResponse = $this->api->approvePayment( $params );
		$response = new ApprovePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->captureResult ) ) {
			$rawStatus = $rawResponse->captureResult->response ?? null;
			$this->prepareResponseObject(
				$response,
				$rawResponse->captureResult,
				new ApprovePaymentStatus(),
				$rawStatus
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
	 * @return CreatePaymentResponse
	 */
	public function cancelPayment( $gatewayTxnId ) {
		$rawResponse = $this->api->cancel( $gatewayTxnId );
		$response = new CancelPaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->cancelResult ) ) {
			$rawStatus = $rawResponse->cancelResult->response ?? null;
			$this->prepareResponseObject(
				$response,
				$rawResponse->cancelResult,
				new CancelPaymentStatus(),
				$rawStatus,
				false
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
	 * Maps errors and other properties from $rawResponse to $response
	 *
	 * @param CreatePaymentResponse $response An instance of a CreatePaymentResponse subclass to be populated
	 * @param object $rawResponse The bit of the API response that has pspReference and refusalReason
	 * @param StatusNormalizer $statusObject An instance of the appropriate status mapper class
	 * @param string $rawStatus The status string from the API response, either from 'resultCode' or 'response'
	 * @param bool $checkForRetry Whether to test the rawStatus against a list of retryable status codes.
	 */
	protected function prepareResponseObject(
		CreatePaymentResponse $response,
		$rawResponse,
		$statusObject,
		$rawStatus,
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
		// map status
		if ( !empty( $rawStatus ) ) {
			$response->setRawStatus( $rawStatus );
			try {
				$status = $statusObject->normalizeStatus( $rawStatus );
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
