<?php namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\TaggedLogger;

class Api {

	/**
	 * Constants set inline with Adyens docs
	 * https://docs.adyen.com/classic-integration/recurring-payments/authorise-a-recurring-payment#recurring-payments
	 */
	const RECURRING_CONTRACT = 'RECURRING';
	const RECURRING_SHOPPER_INTERACTION = 'ContAuth';
	const RECURRING_SELECTED_RECURRING_DETAIL_REFERENCE = 'LATEST';

	/**
	 * @var WSDL\Payment
	 */
	protected $soapClient;
	protected $account;

	public function __construct() {
		$c = Context::get()->getProviderConfiguration();
		$this->account = array_keys( $c->val( "accounts" ) )[0]; // this feels fragile
		$this->soapClient = new WSDL\Payment(
			$c->val( 'payments-wsdl' ),
			[
				'cache_wsdl' => WSDL_CACHE_NONE,
				'login' => $c->val( "accounts/{$this->account}/ws-username" ),
				'password' => $c->val( "accounts/{$this->account}/ws-password" ),
			]
		);
	}

	/**
	 * Create the payment authorisation request.
	 * https://docs.adyen.com/classic-integration/recurring-payments/authorise-a-recurring-payment#recurring-payments
	 *
	 * TODO: This authorise request is currently specific to recurring. Might we want to make non-recurring calls
	 * in the future?
	 *
	 * @param $params
	 * @return bool|WSDL\authoriseResponse
	 */
	public function createPayment( $params ) {
		$data = new WSDL\authorise();
		$data->paymentRequest = new WSDL\PaymentRequest();

		$data->paymentRequest->amount = new WSDL\Amount();
		$data->paymentRequest->amount->currency = $params['currency'];
		$data->paymentRequest->amount->value = $params['amount'] * 100;

		$isRecurring = $params['recurring'] ?? false;
		if ( $isRecurring ) {
			$data->paymentRequest->recurring = new WSDL\Recurring();
			$data->paymentRequest->recurring->contract = static::RECURRING_CONTRACT;
			$data->paymentRequest->shopperInteraction = static::RECURRING_SHOPPER_INTERACTION;
			$data->paymentRequest->selectedRecurringDetailReference = static::RECURRING_SELECTED_RECURRING_DETAIL_REFERENCE;
			$data->paymentRequest->shopperReference = $params['recurring_payment_token'];
		}

		// additional required fields that aren't listed in the docs as being required
		$data->paymentRequest->reference = $params['reference'];
		$data->paymentRequest->merchantAccount = $this->account;

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( 'Launching SOAP authorise request', $data );

		try {
			$response = $this->soapClient->authorise( $data );
		} catch ( \Exception $ex ) {
			Logger::error( 'SOAP authorise request threw exception!', null, $ex );
			return false;
		}

		return $response;
	}

	/**
	 * @param $params
	 * @return bool|WSDL\captureResponse
	 */
	public function approvePayment( $params ) {
		$data = new WSDL\capture();
		$data->modificationRequest = new WSDL\ModificationRequest();
		$data->modificationRequest->modificationAmount = new WSDL\Amount();

		$data->modificationRequest->merchantAccount = $this->account;
		$data->modificationRequest->modificationAmount->currency = $params['currency'];
		$data->modificationRequest->modificationAmount->value = $params['amount'] * 100; // Todo: Make this CLDR aware
		$data->modificationRequest->originalReference = $params['gateway_txn_id'];

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( 'Launching SOAP capture request', $data );

		try {
			$response = $this->soapClient->capture( $data );
		} catch ( \Exception $ex ) {
			Logger::error( 'SOAP capture request threw exception!', null, $ex );
			return false;
		}

		return $response;
	}

	/**
	 * @param $pspReference
	 * @return bool|WSDL\cancelResponse
	 */
	public function cancel( $pspReference ) {
		$data = new WSDL\cancel();
		$data->modificationRequest = new WSDL\ModificationRequest();

		$data->modificationRequest->merchantAccount = $this->account;
		$data->modificationRequest->originalReference = $pspReference;

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( 'Launching SOAP cancel request', $data );

		try {
			$response = $this->soapClient->cancel( $data );
		} catch ( \Exception $ex ) {
			Logger::error( 'SOAP cancel request threw exception!', null, $ex );
			return false;
		}

		return $response;
	}
}
