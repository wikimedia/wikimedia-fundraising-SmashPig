<?php namespace SmashPig\PaymentProviders\Adyen;

interface AdyenPaymentsInterface {

	/**
	 * Indicate which merchant account to use for API calls
	 * @param string $account the merchant account code
	 */
	public function setAccount( $account );

	/**
	 * Performs a Capture modification to a given Adyen transaction.
	 *
	 * @param string $currency Original currency of the request
	 * @param int $amount Amount to be captured. Less than or equal to the original request
	 * @param string $pspReference Original pspReference of the request
	 *
	 * @return bool|string Result will be false on SOAP exception or remote request denial.
	 * If request was successful the return result will be a pspReference string to this modification.
	 */
	public function capture( $currency, $amount, $pspReference );

	/**
	 * Cancels an Adyen authorization
	 *
	 * @param string $pspReference Original pspReference of the request
	 *
	 * @return bool|string Result will be false on SOAP exception or remote request denial.
	 * If request was successful the return result will be a pspReference string to this modification.
	 */
	public function cancel( $pspReference );
}
