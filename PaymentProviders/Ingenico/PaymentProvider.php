<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Mapper\Mapper;
use SmashPig\PaymentProviders\CreatePaymentResponse;

/**
 * Base class for Ingenico payments. Each payment product group should get
 * a concrete subclass implementing PaymentProvider
 */
abstract class PaymentProvider {

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
	 * @return mixed
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
		$response->setRawResponse( $rawResponse );

		if ( isset( $rawResponse["payment"] ) ) {
			// map trxn id
			if ( !empty( $rawResponse["payment"]["id"] ) ) {
				$response->setGatewayTrxnId( $rawResponse["payment"]["id"] );
			} else {
				Logger::debug( 'Unable to map Ingenico gateway transaction ID', $rawResponse );
			}
			// map status
			if ( !empty( $rawResponse["payment"]["status"] ) ) {
				$rawStatus = $rawResponse["payment"]["status"];
				$response->setRawStatus( $rawStatus );
				try {
					$status = ( new PaymentStatus() )->normalizeStatus( $rawStatus );
					$response->setStatus( $status );
				} catch ( \Exception $ex ) {
					$response->addErrors( $ex->getMessage() );
					Logger::debug( 'Unable to map Ingenico status', $rawResponse );
				}
			} else {
				Logger::debug( 'Unable to map Ingenico status', $rawResponse );
			}
			// map errors
			if ( !empty( $rawResponse["payment"]["statusOutput"]['errors'] ) ) {
				$response->addErrors( $rawResponse["payment"]["statusOutput"]['errors'] );
			}

		} else {
			$responseError = 'payment element missing from Ingenico createPayment response.';
			$response->addErrors( $responseError );
			Logger::debug( $responseError, $rawResponse );
		}

		return $response;
	}

	/**
	 * @param $paymentId
	 *
	 * @return mixed
	 */
	public function getPaymentStatus( $paymentId ) {
		$path = "payments/$paymentId";
		$response = $this->api->makeApiCall( $path, 'GET' );
		$this->addPaymentStatusErrorsIfPresent( $response );
		return $response;
	}

	/**
	 * @param $paymentId
	 * @param $params
	 *
	 * @return mixed
	 */
	public function approvePayment( $paymentId, $params ) {
		$path = "payments/$paymentId/approve";
		$response = $this->api->makeApiCall( $path, 'POST', $params );
		$this->addPaymentStatusErrorsIfPresent( $response, $response['payment'] );
		return $response;
	}

	/**
	 * @param $paymentId
	 *
	 * @return mixed
	 */
	public function cancelPayment( $paymentId ) {
		$path = "payments/$paymentId/cancel";
		$response = $this->api->makeApiCall( $path, 'POST' );
		$this->addPaymentStatusErrorsIfPresent( $response, $response['payment'] );
		return $response;
	}

	/**
	 * @param $paymentId
	 *
	 * @return mixed
	 */
	public function tokenizePayment( $paymentId ) {
		$path = "payments/$paymentId/tokenize";
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
	protected function addPaymentStatusErrorsIfPresent( &$response, $paymentResponse=null ) {
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
	public function createRefund( $paymentId, $params ) {
		$path = "payments/$paymentId/refund";
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
}
