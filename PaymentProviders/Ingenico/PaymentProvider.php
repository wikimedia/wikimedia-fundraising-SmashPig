<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Mapper\Mapper;

/**
 * Base class for Ingenico payments. Each payment product group should get
 * a concrete subclass implementing PaymentProvider
 */
abstract class PaymentProvider {

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
			$mapConfig['transformers']
		);
		$response = $this->api->makeApiCall( $path, 'POST', $createPaymentParams );
		$this->addPaymentStatusErrorsIfPresent( $response, $response['payment'] );
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
			Logger::error( $logMessage );
		}
	}
}
