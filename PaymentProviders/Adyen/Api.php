<?php namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Context;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\Http\OutboundRequest;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\TaggedLogger;

class Api {

	/**
	 * Constants set inline with Adyens docs
	 * https://docs.adyen.com/classic-integration/recurring-payments/authorise-a-recurring-payment#recurring-payments
	 * API
	 * https://docs.adyen.com/online-payments/tokenization/create-and-use-tokens?tab=subscriptions_2#make-subscription-payments
	 */
	const RECURRING_CONTRACT = 'RECURRING';
	const RECURRING_SHOPPER_INTERACTION = 'ContAuth';
	const RECURRING_SELECTED_RECURRING_DETAIL_REFERENCE = 'LATEST';
	const RECURRING_PROCESSING_MODEL = 'Subscription';

	/**
	 * @var WSDL\Payment
	 */
	protected $soapClient;

	/**
	 * @var string Name of the merchant account
	 */
	protected $account;

	/**
	 * @var string REST API key
	 */
	protected $apiKey;

	/**
	 * @var string
	 */
	protected $restBaseUrl;

	/**
	 * @var string
	 */
	protected $wsdlEndpoint;

	/**
	 * @var string
	 */
	protected $wsdlUser;

	/**
	 * @var string
	 */
	protected $wsdlPass;

	public function __construct() {
		$c = Context::get()->getProviderConfiguration();
		$this->account = array_keys( $c->val( "accounts" ) )[0]; // this feels fragile
		$this->wsdlEndpoint = $c->val( 'payments-wsdl' );
		$this->wsdlUser = $c->val( "accounts/{$this->account}/ws-username" );
		$this->wsdlPass = $c->val( "accounts/{$this->account}/ws-password" );
		$this->restBaseUrl = $c->val( 'rest-base-url' );
		$this->apiKey = $c->val( "accounts/{$this->account}/ws-api-key" );
	}

	/**
	 * @return WSDL\Payment
	 */
	public function getSoapClient(): WSDL\Payment {
		if ( !$this->soapClient ) {
			$this->soapClient = new WSDL\Payment(
				$this->wsdlEndpoint,
				[
					'cache_wsdl' => WSDL_CACHE_NONE,
					'login' => $this->wsdlUser,
					'password' => $this->wsdlPass,
				]
			);
		}
		return $this->soapClient;
	}

	/**
	 * Uses the rest API to create a payment using a blob of encrypted
	 * payment data as returned by the Drop-In Web integration.
	 *
	 * @param array $params
	 * amount, currency, encrypted_payment_details (blob from front-end)
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPaymentFromEncryptedDetails( $params ) {
		// TODO: use txn template / mapping a la Ingenico?
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'paymentMethod' => $params['encrypted_payment_data'],
			'merchantAccount' => $this->account
		];
		// TODO: map this from $params['payment_method']
		// 'scheme' corresponds to our 'cc' value
		$restParams['paymentMethod']['type'] = 'scheme';
		if ( !empty( $params['return_url'] ) ) {
			$restParams['returnUrl'] = $params['return_url'];
			$parsed = parse_url( $params['return_url'] );
			$restParams['origin'] = $parsed['scheme'] . '://' . $parsed['host'];
			if ( !empty( $parsed['port'] ) ) {
				$restParams['origin'] .= ':' . $parsed['port'];
			}
			// If there is a return URL we are definitely coming via the 'Web' channel
			$restParams['channel'] = 'Web';
		}
		if ( !empty( $params['browser_info'] ) ) {
			$restParams['browserInfo'] = $params['browser_info'];
		}
		$restParams['billingAddress'] = [
			'city' => $params['city'] ?? 'NA',
			'country' => $params['country'] ?? 'ZZ',
			// FIXME do we have to split this out of $params['street_address'] ?
			'houseNumberOrName' => 'NA',
			'postalCode' => $params['postal_code'] ?? 'NA',
			'stateOrProvince' => $params['state_province'] ?? 'NA',
			'street' => $params['street_address'] ?? 'NA'
		];
		$restParams['shopperEmail'] = $params['email'] ?? '';
		$restParams['shopperIP'] = $params['user_ip'] ?? '';
		// TODO: FullName staging helper
		$nameParts = [];
		if ( !empty( $params['first_name'] ) ) {
			$nameParts[] = $params['first_name'];
		}
		if ( !empty( $params['last_name'] ) ) {
			$nameParts[] = $params['last_name'];
		}
		$fullName = implode( ' ', $nameParts );
		$restParams['shopperName'] = $fullName;
		// This is specifically for credit cards
		if ( empty( $restParams['paymentMethod']['holderName'] ) ) {
			$restParams['paymentMethod']['holderName'] = $fullName;
		}
		$restParams['shopperStatement'] = $params['description'] ?? '';
		$isRecurring = $params['recurring'] ?? '';
		if ( $isRecurring ) {
			$restParams['shopperInteraction'] = 'Ecommerce';
			$restParams['shopperReference'] = $params['order_id'];
			$restParams['recurringProcessingModel'] = static::RECURRING_PROCESSING_MODEL;
			$restParams['storePaymentMethod'] = true;
		}
		$result = $this->makeRestApiCall( $restParams, 'payments', 'POST' );
		return $result['body'];
	}

	/**
	 * Uses the rest API to create a payment from a saved token
	 *
	 * @param array $params
	 * amount, currency, payment_method, recurring_payment_token, processor_contact_id
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPaymentFromToken( $params ) {
		$restParams = [
			'amount' => [
				'currency' => $params['currency'],
				'value' => $this->getAmountInMinorUnits(
					$params['amount'], $params['currency']
				)
			],
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account
		];

		$restParams['paymentMethod']['type'] = $params['payment_method'];
		// storedPaymentMethodId - token adyen sends back on auth
		$restParams['paymentMethod']['storedPaymentMethodId'] = $params['recurring_payment_token'];
		$restParams['shopperReference'] = $params['processor_contact_id'];
		$restParams['shopperInteraction'] = static::RECURRING_SHOPPER_INTERACTION;
		$restParams['recurringProcessingModel'] = static::RECURRING_PROCESSING_MODEL;

		// Todo: ideal
		// the documenation looks similiar to above but it has possible differences

		$result = $this->makeRestApiCall( $restParams, 'payments', 'POST' );
		return $result['body'];
	}

	/**
	 * Uses the rest API to create a direct debit payment from the
	 * Component web integration
	 *
	 * @param array $params
	 * amount, currency, value, issuer, returnUrl
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createDirectDebitPaymentFromCheckout( $params ) {
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account,
			'paymentMethod' => [
				'issuer' => $params['issuer_id'],
				// Todo: handle non ideal rtbt
				'type' => 'ideal',
			],
			'returnUrl' => $params['return_url']
		];

		$result = $this->makeRestApiCall( $restParams, 'payments', 'POST' );
		return $result['body'];
	}

	public function createApplePayPayment( $params ) {
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account,
			'paymentMethod' => [
				'type' => 'applepay',
				'applePayToken' => $params['payment_token']
			]
		];

		$result = $this->makeRestApiCall(
			$restParams,
			'payments',
			'POST'
		);

		return $result['body'];
	}

	/**
	 * Gets more details when no final state has been reached
	 * on the /payments call. Redirect payments will need this.
	 *
	 * @param string $redirectResult
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getPaymentDetails( $redirectResult ) {
		$restParams = [
			'details' => [
				'redirectResult' => $redirectResult
			]
		];
		$result = $this->makeRestApiCall( $restParams, 'payments/details', 'POST' );
		return $result['body'];
	}

	/**
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getPaymentMethods( $params ) {
		$restParams = [
			'merchantAccount' => $this->account,
			'countryCode' => $params['country'],
			'amount' => $this->getArrayAmount( $params ),
			'channel' => 'Web',
			// shopperLocale format needs to be language-country nl-NL en-NL
			'shopperLocale' => str_replace( '_', '-', $params['language'] )
		];

		$result = $this->makeRestApiCall( $restParams, 'paymentMethods', 'POST' );
		return $result['body'];
	}

	/**
	 * Uses the rest API to return saved payment details
	 *
	 * @param string $shopperReference
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getSavedPaymentDetails( $shopperReference ) {
		$restParams['merchantAccount'] = $this->account;
		$restParams['shopperReference'] = $shopperReference;

		$result = $this->makeRestApiCall( $restParams, 'paymentMethods', 'POST' );
		return $result['body'];
	}

	/**
	 * @throws \SmashPig\Core\ApiException
	 */
	protected function makeRestApiCall( $params, $path, $method ) {
		$url = $this->restBaseUrl . '/' . $path;
		$request = new OutboundRequest( $url, $method );
		$request->setBody( json_encode( $params ) );
		$request->setHeader( 'x-API-key', $this->apiKey );
		$request->setHeader( 'content-type', 'application/json' );
		$response = $request->execute();
		$response['body'] = json_decode( $response['body'], true );
		ExceptionMapper::throwOnAdyenError( $response['body'] );
		return $response;
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
		$data->paymentRequest->amount = $this->getWsdlAmountObject( $params );

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
			$response = $this->getSoapClient()->authorise( $data );
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
		$data->request->amount = $this->getWsdlAmountObject( $params );

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
			$response = $this->getSoapClient()->directdebit( $data );
			Logger::debug( $this->getSoapClient()->__getLastRequest() );
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
	 * @return bool|array
	 */
	public function approvePayment( $params ) {
		$restParams = [
			'amount' => [
				'currency' => $params['currency'],
				'value' => $this->getAmountInMinorUnits(
					$params['amount'], $params['currency']
				)
			],
			'merchantAccount' => $this->account
		];
		$path = "payments/{$params['gateway_txn_id']}/captures";

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( "Launching REST capture request for {$params['gateway_txn_id']}", $restParams );

		try {
			$result = $this->makeRestApiCall( $restParams, $path, 'POST' );
		} catch ( \Exception $ex ) {
			// FIXME shouldn't we let the ApiException bubble up?
			Logger::error( 'REST capture request threw exception!', $params, $ex );
			return false;
		}
		return $result['body'];
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
			$response = $this->getSoapClient()->cancel( $data );
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
	private function getWsdlAmountObject( array $params ): WSDL\Amount {
		$amount = new WSDL\Amount();
		$amount->value = $this->getAmountInMinorUnits( $params['amount'], $params['currency'] );
		$amount->currency = $params['currency'];
		return $amount;
	}

	/**
	 * Convenience function for formatting amounts in REST calls
	 *
	 * @param array $params
	 * @return array
	 */
	private function getArrayAmount( array $params ): array {
		return [
			'currency' => $params['currency'],
			'value' => $this->getAmountInMinorUnits(
				$params['amount'], $params['currency']
			)
		];
	}

	/**
	 * Adyen requires amounts to be passed as an integer representing the value
	 * in minor units for that currency. Currencies that lack a minor unit
	 * (such as JPY) are simply passed as is. For example: USD 10.50 would be
	 * changed to 1050, JPY 150 would be passed as 150.
	 *
	 * @param float $amount The amount in major units
	 * @param string $currency ISO currency code
	 * @return int The amount in minor units
	 */
	private function getAmountInMinorUnits( float $amount, string $currency ): int {
		if ( CurrencyRoundingHelper::isThreeDecimalCurrency( $currency ) ) {
			$amount = $amount * 1000;
		} elseif ( CurrencyRoundingHelper::isFractionalCurrency( $currency ) ) {
			$amount = $amount * 100;
		}
		return (int)$amount;
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
