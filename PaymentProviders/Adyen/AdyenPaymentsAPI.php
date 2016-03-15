<?php namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\TaggedLogger;

class AdyenPaymentsAPI implements AdyenPaymentsInterface {

	protected $soapClient = null;
	protected $account = '';

	public function setAccount( $account ) {
		require_once( 'WSDL/Payment.php' );

		$this->account = $account;

		$c = Context::get()->getConfiguration();
		$this->soapClient = new WSDL\Payment(
			$c->val( 'payment-provider/adyen/payments-wsdl' ),
			array(
				 'cache_wsdl' => WSDL_CACHE_BOTH,
				 'login'      => $c->val( "payment-provider/adyen/accounts/{$this->account}/ws-username" ),
				 'password'   => $c->val( "payment-provider/adyen/accounts/{$this->account}/ws-password" ),
			)
		);
	}

	public function capture( $currency, $amount, $pspReference ) {
		$data = new WSDL\capture();
		$data->modificationRequest = new WSDL\ModificationRequest();
		$data->modificationRequest->modificationAmount = new WSDL\Amount();

		$data->modificationRequest->merchantAccount = $this->account;
		$data->modificationRequest->modificationAmount->currency = $currency;
		$data->modificationRequest->modificationAmount->value = $amount * 100; // Todo: Make this CLDR aware
		$data->modificationRequest->originalReference = $pspReference;

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( 'Launching SOAP capture request', $data );

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

	public function cancel( $pspReference ) {
		$data = new WSDL\cancel();
		$data->modificationRequest = new WSDL\ModificationRequest();

		$data->modificationRequest->merchantAccount = $this->account;
		$data->modificationRequest->originalReference = $pspReference;

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( 'Launching SOAP capture request', $data );

		try {
			$resp = $this->soapClient->cancel( $data );
		} catch ( \Exception $ex ) {
			Logger::error( 'SOAP capture request threw exception!', null, $ex );
			return false;
		}

		if ( $resp->captureResult->response == '[cancel-received]' ) {
			return $resp->captureResult->pspReference;
		} else {
			Logger::error( 'SOAP capture request did not work as expected!', $resp );
			return false;
		}
	}
}
