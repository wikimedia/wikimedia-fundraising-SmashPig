<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\PaymentProviders\IPaymentProvider;

class MockAdyenPaymentsAPI implements IPaymentProvider {

	protected $account = '';
	protected $captureReturnCode = false;
	protected $cancelReturnCode = false;

	public $captured = [];
	public $cancelled = [];

	public function __construct( $returnCode ) {
		if ( $returnCode == 'Success!' ) {
			$this->captureReturnCode = '[capture-received]';
			$this->cancelReturnCode = '[cancel-received]';
		}
	}

	public function setAccount( $account ) {
		$this->account = $account;
	}

	/*
	 * Yet to be faked.
	 */
	public function createPayment( $params ) {
		return;
	}

	/**
	 * Fakes a Capture modification to a given Adyen transaction.
	 *
	 * @param array $params
	 * @return \stdClass
	 */
	public function approvePayment( $params ) {
		$this->captured[] = [
			'currency' => $params['currency'],
			'amount' => $params['amount'],
			'pspReference' => $params['payment_id'],
		];

		$response = json_decode( json_encode(
			[ 'captureResult' => [
				'response' => $this->captureReturnCode,
				'pspReference' => $params['payment_id']
			] ] ) );

		return $response;
	}

	/**
	 * Pretends to cancel an Adyen authorization
	 *
	 * @param string $pspReference Original pspReference of the request
	 * @return \stdClass
	 */
	public function cancel( $pspReference ) {
		$this->cancelled[] = $pspReference;

		$response = json_decode( json_encode(
			[ 'cancelResult' => [
				'response' => $this->cancelReturnCode,
				'pspReference' => $pspReference
			] ] ) );

		return $response;
	}
}
