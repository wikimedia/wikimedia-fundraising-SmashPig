<?php namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\PaymentProviders\PaymentProviderInterface;

class AdyenPaymentsAPI implements PaymentProviderInterface {

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
	protected $soapClient = null;
	protected $account = '';

	public function setAccount( $account ) {
		require_once 'WSDL/Payment.php';

		$this->account = $account;

		$c = Context::get()->getProviderConfiguration();
		$this->soapClient = new WSDL\Payment(
			$c->val( 'payments-wsdl' ),
			[
				 'cache_wsdl' => WSDL_CACHE_NONE,
				 'login'      => $c->val( "accounts/{$this->account}/ws-username" ),
				 'password'   => $c->val( "accounts/{$this->account}/ws-password" ),
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
	 * @return bool|string
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
			$data->paymentRequest->shopperReference = $params['token'];
		}

		// additional required fields that aren't listed in the docs as being required
		$data->paymentRequest->reference = $params['reference'];
		$data->paymentRequest->merchantAccount = $this->account;

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( 'Launching SOAP authorise request', $data );

		try {
			$resp = $this->soapClient->authorise( $data );
		} catch ( \Exception $ex ) {
			Logger::error( 'SOAP authorise request threw exception!', null, $ex );
			return false;
		}

		if ( $resp->paymentResult->resultCode == "Authorised" ) {
			return $resp->paymentResult->pspReference;
		} else {
			Logger::error( 'SOAP authorise request did not work as expected!', $resp );
			return false;
		}
	}

	public function approvePayment( $paymentId, $params ) {
		$data = new WSDL\capture();
		$data->modificationRequest = new WSDL\ModificationRequest();
		$data->modificationRequest->modificationAmount = new WSDL\Amount();

		$data->modificationRequest->merchantAccount = $this->account;
		$data->modificationRequest->modificationAmount->currency = $params['currency'];
		$data->modificationRequest->modificationAmount->value = $params['amount'] * 100; // Todo: Make this CLDR aware
		$data->modificationRequest->originalReference = $paymentId;

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
		$tl->info( 'Launching SOAP cancel request', $data );

		try {
			$resp = $this->soapClient->cancel( $data );
		} catch ( \Exception $ex ) {
			Logger::error( 'SOAP cancel request threw exception!', null, $ex );
			return false;
		}

		if ( $resp->cancelResult->response == '[cancel-received]' ) {
			return $resp->cancelResult->pspReference;
		} else {
			Logger::error( 'SOAP cancel request did not work as expected!', $resp );
			return false;
		}
	}
}
