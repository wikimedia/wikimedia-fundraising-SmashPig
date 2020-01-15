<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\PaymentProviders\PaymentProviderInterface;

class MockAdyenPaymentsAPI implements PaymentProviderInterface {

	protected $account = '';
	protected $returnCode = false;

	public $captured = [];
	public $cancelled = [];

	public function __construct( $returnCode ) {
		$this->returnCode = $returnCode;
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
	 * @param string $paymentId Original pspReference of the request
	 * @param array $params
	 * @return bool|string The return code set in the constructor.
	 */
	public function approvePayment( $paymentId, $params ) {
		$this->captured[] = [
			'currency' => $params['currency'],
			'amount' => $params['amount'],
			'pspReference' => $paymentId,
		];
		return $this->returnCode;
	}

	/**
	 * Pretends to cancel an Adyen authorization
	 *
	 * @param string $pspReference Original pspReference of the request
	 * @return bool|string The return code set in the constructor.
	 */
	public function cancel( $pspReference ) {
		$this->cancelled[] = $pspReference;
		return $this->returnCode;
	}
}
