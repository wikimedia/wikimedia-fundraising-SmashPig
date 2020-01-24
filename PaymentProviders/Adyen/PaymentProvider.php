<?php


namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
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
				Logger::debug( 'Unable to map Adyen Gateway Transaction ID', $rawResponse );
			}
			// map status
			if ( !empty( $rawResponse->paymentResult->resultCode ) ) {
				$rawStatus = $rawResponse->paymentResult->resultCode;
				$response->setRawStatus( $rawStatus );
				try {
					$status = ( new CreatePaymentStatus() )->normalizeStatus( $rawStatus );
					$response->setStatus( $status );
				} catch ( \Exception $ex ) {
					$response->addErrors( $ex->getMessage() );
					Logger::debug( 'Unable to map Adyen Status', $rawResponse );
				}
			} else {
				Logger::debug( 'Unable to map Adyen Status', $rawResponse );
			}
			// map errors
			if ( !empty( $rawResponse->paymentResult->refusalReason ) ) {
				$response->addErrors( $rawResponse->paymentResult->refusalReason );
			}
		} else {
			$responseError = 'paymentResult element missing from Adyen createPayment response. ';
			$response->addErrors( $responseError );
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
				Logger::debug( 'Unable to map Adyen Gateway Transaction ID', $rawResponse );
			}
			// map status
			if ( !empty( $rawResponse->captureResult->response ) ) {
				$rawStatus = $rawResponse->captureResult->response;
				$response->setRawStatus( $rawStatus );
				try {
					$status = ( new ApprovePaymentStatus() )->normalizeStatus( $rawStatus );
					$response->setStatus( $status );
				} catch ( \Exception $ex ) {
					$response->addErrors( $ex->getMessage() );
					Logger::debug( 'Unable to map Adyen Status', $rawResponse );
				}
			} else {
				Logger::debug( 'Unable to map Adyen Status', $rawResponse );
			}
			// map errors
			if ( !empty( $rawResponse->captureResult->refusalReason ) ) {
				$response->addErrors( $rawResponse->captureResult->refusalReason );
			}
		} else {
			$responseError = 'captureResult element missing from Adyen approvePayment response.';
			$response->addErrors( $responseError );
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
				Logger::debug( 'Unable to map Adyen Gateway Transaction ID', $rawResponse );
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
				Logger::debug( 'Unable to map Adyen Status', $rawResponse );
			}
			// map errors
			if ( !empty( $rawResponse->cancelResult->refusalReason ) ) {
				$response->addErrors( $rawResponse->cancelResult->refusalReason );
			}
		} else {
			$responseError = 'cancelResult element missing from Adyen cancel response.';
			$response->addErrors( $responseError );
			Logger::debug( $responseError, $rawResponse );
		}

		return $response;
	}
}
