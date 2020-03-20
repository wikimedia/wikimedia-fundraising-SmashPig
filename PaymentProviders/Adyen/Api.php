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
	 * Requests authorisation of a credit card payment.
	 * https://docs.adyen.com/classic-integration/recurring-payments/authorise-a-recurring-payment#recurring-payments
	 *
	 * TODO: This authorise request is currently specific to recurring. Might we want to make non-recurring calls
	 * in the future?
	 *
	 * @param array $params needs 'recurring_payment_token', 'order_id', 'recurring', 'amount', and 'currency'
	 * @return bool|WSDL\authoriseResponse
	 */
	public function createPayment( $params ) {
		$data = new WSDL\authorise();
		$data->paymentRequest = new WSDL\PaymentRequest();
		$data->paymentRequest->amount = $this->getAmount( $params );

		$isRecurring = $params['recurring'] ?? false;
		if ( $isRecurring ) {
			$data->paymentRequest->recurring = $this->getRecurring();
			$data->paymentRequest->shopperInteraction = static::RECURRING_SHOPPER_INTERACTION;
			$data->paymentRequest->selectedRecurringDetailReference = static::RECURRING_SELECTED_RECURRING_DETAIL_REFERENCE;
			$data->paymentRequest->shopperReference = $params['recurring_payment_token'];
		}

		// additional required fields that aren't listed in the docs as being required
		$data->paymentRequest->reference = $params['order_id'];
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
	 * Requests a direct debit payment. As with the card payment, this function currently only
	 * supports recurring payments.
	 * Documentation for the classic integration is no longer available, but there's this:
	 * https://docs.adyen.com/payment-methods/sepa-direct-debit/api-only#recurring-payments
	 *
	 * @param array $params needs 'recurring_payment_token', 'order_id', 'recurring', 'amount', and 'currency'
	 * @return bool|WSDL\directdebitFuncResponse
	 */
	public function createDirectDebitPayment( $params ) {
		$data = new WSDL\directdebit();
		$data->request = new WSDL\DirectDebitRequest();
		$data->request->amount = $this->getAmount( $params );

		$isRecurring = $params['recurring'] ?? false;
		if ( $isRecurring ) {
			$data->request->recurring = $this->getRecurring();
			$data->request->shopperInteraction = self::RECURRING_SHOPPER_INTERACTION;
			$data->request->selectedRecurringDetailReference = self::RECURRING_SELECTED_RECURRING_DETAIL_REFERENCE;
			$data->request->shopperReference = $params['recurring_payment_token'];
		}

		$data->request->reference = $params['order_id'];
		$data->request->merchantAccount = $this->account;

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( 'Launching SOAP directdebit request', $data );

		try {
			$response = $this->soapClient->directdebit( $data );
			Logger::debug( $this->soapClient->__getLastRequest() );
		} catch ( \Exception $ex ) {
			Logger::error( 'SOAP directdebit request threw exception!', null, $ex );
			return false;
		}

		return $response;
	}

	/**
	 * Approve a payment that has been authorized. In credit-card terms, this
	 * captures the payment.
	 *
	 * @param array $params Needs keys 'gateway_txn_id', 'currency', and 'amount' set
	 * @return bool|WSDL\captureResponse
	 */
	public function approvePayment( $params ) {
		$data = new WSDL\capture();
		$data->modificationRequest = new WSDL\ModificationRequest();
		$data->modificationRequest->modificationAmount = $this->getAmount( $params );
		$data->modificationRequest->merchantAccount = $this->account;
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
	 * Cancels a payment that may already be authorized
	 *
	 * @param string $pspReference The Adyen-side identifier, aka gateway_txn_id
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

	/**
	 * @param array $params
	 * @return WSDL\Amount
	 */
	private function getAmount( $params ) {
		$amount = new WSDL\Amount();
		$amount->currency = $params['currency'];
		$amount->value = $params['amount'] * 100;
		return $amount;
	}

	/**
	 * @return WSDL\Recurring
	 */
	private function getRecurring() {
		$recurring = new WSDL\Recurring();
		$recurring->contract = static::RECURRING_CONTRACT;
		return $recurring;
	}
}
