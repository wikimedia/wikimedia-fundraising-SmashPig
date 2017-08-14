<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\PaymentProviders\Adyen\AdyenPaymentsInterface;

class MockAdyenPaymentsAPI implements AdyenPaymentsInterface {

	protected $account = '';
	protected $returnCode = false;

	public $captured = array();
	public $cancelled = array();

	public function __construct( $returnCode ) {
		$this->returnCode = $returnCode;
	}

	public function setAccount( $account ) {
		$this->account = $account;
	}

	/**
	 * Fakes a Capture modification to a given Adyen transaction.
	 *
	 * @param string $currency Original currency of the request
	 * @param int $amount Amount to be captured. Less than or equal to the original request
	 * @param string $pspReference Original pspReference of the request
	 *
	 * @returns bool|string The return code set in the constructor.
	 */
	public function capture( $currency, $amount, $pspReference ) {
		$this->captured[] = array(
			'currency' => $currency,
			'amount' => $amount,
			'pspReference' => $pspReference,
		);
		return $this->returnCode;
	}

	/**
	 * Pretends to cancel an Adyen authorization
	 *
	 * @param string $pspReference Original pspReference of the request
	 *
	 * @returns bool|string The return code set in the constructor.
	 */
	public function cancel( $pspReference ) {
		$this->cancelled[] = $pspReference;
		return $this->returnCode;
	}
}
