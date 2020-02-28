<?php


namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
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
class PaymentProvider implements IPaymentProvider {
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
	 * @param $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( $params ) {
		$rawResponse = $this->api->createPayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->paymentResult ) ) {
			// map trxn id
			if ( !empty( $rawResponse->paymentResult->pspReference ) ) {
				$response->setGatewayTrxnId( $rawResponse->paymentResult->pspReference );
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
			if ( !empty( $rawResponse->paymentResult->resultCode ) ) {
				$rawStatus = $rawResponse->paymentResult->resultCode;
				$response->setRawStatus( $rawStatus );
				try {
					$status = ( new CreatePaymentStatus() )->normalizeStatus( $rawStatus );
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
			if ( !empty( $rawResponse->paymentResult->refusalReason ) ) {
				if ( !$this->canRetryRefusalReason( $rawResponse->paymentResult->refusalReason ) ) {
					$response->addErrors( new PaymentError(
						ErrorCode::DECLINED_DO_NOT_RETRY,
						$rawResponse->paymentResult->refusalReason,
						LogLevel::INFO
					) );
				}
			}
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

	/**
	 * @param $params
	 * @return CreatePaymentResponse
	 */
	public function approvePayment( $params ) {
		$rawResponse = $this->api->approvePayment( $params );
		$response = new ApprovePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->captureResult ) ) {
			// map trxn id
			if ( !empty( $rawResponse->captureResult->pspReference ) ) {
				$response->setGatewayTrxnId( $rawResponse->captureResult->pspReference );
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
			if ( !empty( $rawResponse->captureResult->response ) ) {
				$rawStatus = $rawResponse->captureResult->response;
				$response->setRawStatus( $rawStatus );
				try {
					$status = ( new ApprovePaymentStatus() )->normalizeStatus( $rawStatus );
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
			if ( !empty( $rawResponse->captureResult->refusalReason ) ) {
				if ( !$this->canRetryRefusalReason( $rawResponse->paymentResult->refusalReason ) ) {
					$response->addErrors( new PaymentError(
						ErrorCode::DECLINED_DO_NOT_RETRY,
						$rawResponse->paymentResult->refusalReason,
						LogLevel::INFO
					) );
				}
			}
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
	 * @param $params
	 * @return CreatePaymentResponse
	 */
	public function cancel( $params ) {
		$rawResponse = $this->api->cancel( $params );
		$response = new CancelPaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->cancelResult ) ) {
			// map trxn id
			if ( !empty( $rawResponse->cancelResult->pspReference ) ) {
				$response->setGatewayTrxnId( $rawResponse->cancelResult->pspReference );
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
			if ( !empty( $rawResponse->cancelResult->response ) ) {
				$rawStatus = $rawResponse->cancelResult->response;
				$response->setRawStatus( $rawStatus );
				try {
					$status = ( new CancelPaymentStatus() )->normalizeStatus( $rawStatus );
					$response->setStatus( $status );
				} catch ( \Exception $ex ) {
					$response->addErrors( $ex->getMessage() );
					Logger::debug( 'Unable to map Adyen Status', $rawResponse );
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
			if ( !empty( $rawResponse->cancelResult->refusalReason ) ) {
				// Weird, these are mostly for auth and capture declines!
				$response->addErrors( new PaymentError(
					ErrorCode::UNEXPECTED_VALUE,
					"Cancel yielded refusal reason '{$rawResponse->cancelResult->refusalReason}'",
					LogLevel::ERROR
				) );
			}
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
	 * Documented at
	 * https://docs.adyen.com/development-resources/refusal-reasons#page-introduction
	 *
	 * @param string $refusalReason
	 * @return bool
	 */
	private function canRetryRefusalReason( $refusalReason ) {
		$noRetryReasons = [
			'800 No Contract Found',
			'Acquirer Fraud',
			'Blocked Card',
			'FRAUD',
			'FRAUD-CANCELLED',
			'Invalid Amount',
			'Invalid Card Number',
			'Invalid Pin',
			'Pin validation not possible',
			'Referral',
			'Restricted Card',
			'Revocation Of Auth',
		];
		if ( in_array( $refusalReason, $noRetryReasons ) ) {
			return false;
		}
		return true;
	}
}
