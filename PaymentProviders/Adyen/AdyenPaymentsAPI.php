<?php namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Configuration;

class AdyenPaymentsAPI {

	protected $soapClient = null;
	protected $account = '';

	public function __construct( $account ) {
		require_once( 'WSDL/Payment.php' );

		$this->account = $account;

		$this->soapClient = new WSDL\Payment(
			Configuration::getDefaultConfig()->val( 'payment-provider/adyen/payments-wsdl' ),
			array(
				 'cache_wsdl' => WSDL_CACHE_BOTH,
				 'login'      => Configuration::getDefaultConfig()->val(
					 "payment-provider/adyen/accounts/{$this->account}/ws-username"
				 ),
				 'password'   => Configuration::getDefaultConfig()->val(
					 "payment-provider/adyen/accounts/{$this->account}/ws-password"
				 ),
			)
		);
	}

	/**
	 * Performs a Capture modification to a given Adyen transaction.
	 *
	 * @param string    $currency       Original currency of the request
	 * @param int       $amount         Amount to be captured. Less than or equal to the original request
	 * @param string    $pspReference   Original pspReference of the request
	 *
	 * @returns bool|string Result will be false on SOAP exception or remote request denial. If request was successful
	 * the return result will be a pspReference string to this modification.
	 */
	public function capture( $currency, $amount, $pspReference ) {
		$data = new WSDL\capture();
		$data->modificationRequest = new WSDL\ModificationRequest();
		$data->modificationRequest->modificationAmount = new WSDL\Amount();

		$data->modificationRequest->merchantAccount = $this->account;
		$data->modificationRequest->modificationAmount->currency = $currency;
		$data->modificationRequest->modificationAmount->value = floor( $amount * 100 ); // Todo: Make this CLDR aware
		$data->modificationRequest->originalReference = $pspReference;

		Logger::enterContext( 'RawData' );
		Logger::info( 'Launching SOAP capture request', $data );
		Logger::leaveContext();

		try {
			$resp = $this->soapClient->capture( $data );
		} catch ( \Exception $ex ) {
			Logger::error( 'SOAP capture request threw exception!', null, $ex );
			return false;
		}

		if ( $resp->captureResult->response == '[capture-received]' ) {
			return $resp->captureResult->pspReference;
		} else {
			Logger::error( 'SOAP capture request did not work as expected!', $resp );
			return false;
		}
	}
}
