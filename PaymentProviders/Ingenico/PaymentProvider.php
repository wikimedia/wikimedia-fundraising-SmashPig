<?php

namespace SmashPig\PaymentProviders\Ingenico;

use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Mapper\Mapper;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\CreatePaymentResponse;
use SmashPig\PaymentProviders\ApprovePaymentResponse;
use SmashPig\PaymentProviders\IPaymentProvider;

/**
 * Base class for Ingenico payments. Each payment product group should get
 * a concrete subclass implementing PaymentProvider
 */
abstract class PaymentProvider implements IPaymentProvider {

	/**
	 * @var Api
	 */
	protected $api;

	protected $providerConfiguration;

	/**
	 * PaymentProvider constructor.
	 *
	 * @param array $options
	 *
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function __construct( $options = [] ) {
		// FIXME: provide objects in constructor
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}

	/**
	 * @param array $params
	 *
	 * @return CreatePaymentResponse
	 */
	public function createPayment( $params ) {
		$path = "payments";
		$mapConfig = $this->providerConfiguration->val( 'maps/create-payment' );
		$createPaymentParams = Mapper::map(
			$params,
			$mapConfig['path'],
			$mapConfig['transformers'],
			null,
			true
		);

		$rawResponse = $this->api->makeApiCall( $path, 'POST', $createPaymentParams );
		$response = new CreatePaymentResponse();
		$this->prepareResponseObject( $response, $rawResponse );

		return $response;
	}

	/**
	 * @param $paymentId
	 *
	 * @return mixed
	 */
	public function getPaymentStatus( $gatewayTxnId ) {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$path = "payments/$gatewayTxnId";
		$response = $this->api->makeApiCall( $path, 'GET' );
		$this->addPaymentStatusErrorsIfPresent( $response );
		return $response;
	}

	/**
	 * @param $paymentId
	 * @param $params
	 *
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( $params ) {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$gatewayTxnId = $params['gateway_txn_id'];
		$path = "payments/$gatewayTxnId/approve";
		$rawResponse = $this->api->makeApiCall( $path, 'POST', $params );

		$response = new ApprovePaymentResponse();
		$this->prepareResponseObject( $response, $rawResponse );

		return $response;
	}

	/**
	 * @param $paymentId
	 *
	 * @return mixed
	 */
	public function cancelPayment( $gatewayTxnId ) {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$path = "payments/$gatewayTxnId/cancel";
		$response = $this->api->makeApiCall( $path, 'POST' );
		$this->addPaymentStatusErrorsIfPresent( $response, $response['payment'] );
		return $response;
	}

	/**
	 * @param $paymentId
	 *
	 * @return mixed
	 */
	public function tokenizePayment( $gatewayTxnId ) {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$path = "payments/$gatewayTxnId/tokenize";
		$response = $this->api->makeApiCall( $path, 'POST' );
		return $response;
	}

	/**
	 * Check for the presence of payment status response errors and if present
	 * log and add them to the top-level response.
	 *
	 * @param $paymentResponse
	 * @param $response
	 */
	protected function addPaymentStatusErrorsIfPresent( &$response, $paymentResponse = null ) {
		if ( $paymentResponse === null ) {
			$paymentResponse = $response;
		}

		if ( $this->hasPaymentStatusErrors( $paymentResponse ) ) {
			$response['errors'] = $this->getPaymentStatusErrors( $paymentResponse );
			$this->logPaymentStatusErrors( $response['errors'] );
		}
	}

	/**
	 * @param array $paymentResponse
	 *
	 * @return bool
	 */
	protected function hasPaymentStatusErrors( $paymentResponse ) {
		if ( isset( $paymentResponse['statusOutput'] ) &&
			!empty( $paymentResponse['statusOutput']['errors'] ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This is the first step in refunding a payment. You will need to use
	 * the ID from the result of this method and call approveRefund in order
	 * for the donor to actually get their money back.
	 * API call is documented at
	 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/payments/refund.html#payments-refund
	 *
	 * @param string $paymentId The full Ingenico payment ID
	 * @param array $params needs these keys set:
	 *  currency,
	 *  amount (in major units, e.g. dollars),
	 *  first_name,
	 *  last_name,
	 *  order_id,
	 *  country
	 * @return array
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function createRefund( $gatewayTxnId, $params ) {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$path = "payments/$gatewayTxnId/refund";
		$mapConfig = $this->providerConfiguration->val( 'maps/refund-payment' );
		$createRefundParams = Mapper::map(
			$params,
			$mapConfig['path'],
			$mapConfig['transformers'],
			null,
			true
		);
		$response = $this->api->makeApiCall( $path, 'POST', $createRefundParams );
		$this->addPaymentStatusErrorsIfPresent( $response );
		return $response;
	}

	/**
	 * This is the second step to refunding a payment, documented at
	 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/refunds/approve.html#refunds-approve
	 *
	 * Note that the REST API accepts an 'amount' parameter in case you want to
	 * approve a different amount, but we have omitted that possibility.
	 *
	 * @param string $refundId the ID generated by createRefund
	 * @return array with errors if an error happened, otherwise empty
	 */
	public function approveRefund( $refundId ) {
		$path = "refunds/$refundId/approve";
		// Need to POST something, even just an empty array
		$response = $this->api->makeApiCall( $path, 'POST', [] );
		return $response;
	}

	/**
	 * Currently we send these back verbatim to DonationInterface
	 *
	 * In future we might map these to
	 * https://github.com/Ingenico-ePayments/connect-sdk-php/blob/master/src/Ingenico/Connect/Sdk/Domain/Errors/Definitions/APIError.php
	 *
	 * @param $paymentResponse
	 *
	 * @return bool
	 */
	protected function getPaymentStatusErrors( $paymentResponse ) {
		if ( isset( $paymentResponse['statusOutput'] ) &&
			!empty( $paymentResponse['statusOutput']['errors'] ) ) {
			return $paymentResponse['statusOutput']['errors'];
		} else {
			return false;
		}
	}

	/**
	 * @param $errors
	 */
	protected function logPaymentStatusErrors( $errors ) {
		foreach ( $errors as $error ) {
			$logMessage = "Error code {$error['code']}: {$error['message']}.";
			Logger::warning( $logMessage );
		}
	}

	/**
	 * @param CreatePaymentResponse $response
	 * @param array $rawResponse
	 */
	protected function prepareResponseObject( CreatePaymentResponse $response, $rawResponse ) {
		$response->setRawResponse( $rawResponse );
		if ( isset( $rawResponse['errors'] ) ) {
			$response->addErrors(
				$this->mapErrors( $rawResponse['errors'] )
			);
		}

		if ( isset( $rawResponse['payment'] ) ) {
			$rootPaymentNode = $rawResponse['payment'];
		} elseif ( isset( $rawResponse['paymentResult']['payment'] ) ) {
			$rootPaymentNode = $rawResponse['paymentResult']['payment'];
		} else {
			if ( $response->hasErrors() ) {
				// There is already a top-level error code which may have prevented
				// any payment creation. No need to add another error.
				return;
			}
			$responseError = 'payment element missing from Ingenico response.';
			$response->addErrors(
				new PaymentError(
					ErrorCode::MISSING_REQUIRED_DATA,
					$responseError,
					LogLevel::ERROR
				)
			);
			Logger::debug( $responseError, $rawResponse );
			return;
		}
		// map trxn id
		if ( !empty( $rootPaymentNode['id'] ) ) {
			$response->setGatewayTxnId( $rootPaymentNode['id'] );
		} else {
			$message = 'Unable to map Ingenico gateway transaction ID';
			$response->addErrors(
				new PaymentError(
					ErrorCode::MISSING_TRANSACTION_ID,
					$message,
					LogLevel::ERROR
				)
			);
			Logger::debug( $message, $rawResponse );
		}
		// map status
		if ( !empty( $rootPaymentNode['status'] ) ) {
			$rawStatus = $rootPaymentNode['status'];
			$response->setRawStatus( $rawStatus );
			try {
				$status = ( new PaymentStatus() )->normalizeStatus( $rawStatus );
				$response->setStatus( $status );
			} catch ( \Exception $ex ) {
				$response->addErrors(
					new PaymentError(
						ErrorCode::UNEXPECTED_VALUE,
						$ex->getMessage(),
						LogLevel::ERROR
					)
				);
				Logger::debug( 'Unable to map Ingenico status', $rawResponse );
			}
		} else {
			Logger::debug( 'Unable to map Ingenico status', $rawResponse );
		}
		// map errors
		if ( !empty( $rootPaymentNode['statusOutput']['errors'] ) ) {
			$response->addErrors( $this->mapErrors( $rootPaymentNode['statusOutput']['errors'] ) );
		}
	}

	/**
	 * @param array $errors
	 * @return PaymentError[]
	 */
	protected function mapErrors( $errors ) {
		$errorMap = [
			'20000000' => ErrorCode::MISSING_REQUIRED_DATA,
			// TODO: handle 400120 which is ErrorCode::DUPLICATE_ORDER_ID when the TXN is INSERT_ORDERWITHPAYMENT
			'400490' => ErrorCode::DUPLICATE_ORDER_ID,
			'300620' => ErrorCode::DUPLICATE_ORDER_ID,
			'430260' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430349' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430357' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430410' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430415' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430418' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430421' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430697' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'485020' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'4360022' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'4360023' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430306' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430330' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430354' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430285' => ErrorCode::DECLINED,
			'430396' => ErrorCode::DECLINED,
			'430409' => ErrorCode::DECLINED,
			'430424' => ErrorCode::DECLINED,
			'430692' => ErrorCode::DECLINED,
			'11000400' => ErrorCode::SERVER_TIMEOUT,
			// TODO: handle 20001000 and 21000050 validation problems
		];
		$mappedErrors = [];
		foreach ( $errors as $error ) {
			if ( isset( $errorMap[$error['code']] ) ) {
				$mappedCode = $errorMap[$error['code']];
				$logLevel = LogLevel::INFO;
			} else {
				$mappedCode = ErrorCode::UNKNOWN;
				$logLevel = LogLevel::ERROR;
			}
			$mappedErrors[] = new PaymentError(
				$mappedCode,
				json_encode( $error ),
				$logLevel
			);
		}
		return $mappedErrors;
	}
}
