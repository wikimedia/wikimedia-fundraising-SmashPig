<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\ApiException;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\TaggedLogger;

class Api {

	private $merchantAccountId;

	private $GravyApiClient;

	public function __construct() {
		$c = Context::get()->getProviderConfiguration();

		$privateKeyLocation = $c->val( 'privateKeyLocation' );
		$gravyId = $c->val( 'gravy-id' );
		$apiPrefix = $c->val( 'api-prefix' );

		$this->merchantAccountId = $c->val( 'merchantAccountId' );
		$this->GravyApiClient = new Gr4vy\Gr4vyConfig( $gravyId, $privateKeyLocation, true, $apiPrefix, $this->merchantAccountId );
	}

	/**
	 * Creates a new checkout session
	 */
	public function createPaymentSession( $params = [] ) {
		$response = $this->GravyApiClient->newCheckoutSession( $params );
		$tl = new TaggedLogger( 'RawData' );
		$response_string = json_encode( $response );
		$tl->info( "New Checkout Session response $response_string" );
		return $response;
	}

	/**
	 * Initializes the two step payment process
	 *
	 *
	 * @param array $params
	 * amount, currency, payment_method
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/new-transaction Gr4vy Documentation to create a new transaction
	 */
	public function createPayment( array $params ): array {
		$response = $this->GravyApiClient->authorizeNewTransaction( $params );
		$tl = new TaggedLogger( 'RawData' );
		$response_string = json_encode( $response );
		$tl->info( "Create payment response $response_string" );
		return $response;
	}

	/**
	 * Uses the rest API to capture the payment using the transaction ID
	 * received from the createPayment request
	 *
	 * @param array $params
	 * gateway_txn_id, amount
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/capture-transaction Documentation to approve payment
	 */
	public function approvePayment( array $params ): array {
		$trxn_id = $params['gateway_txn_id'];
		if ( empty( $trxn_id ) ) {
			throw new ApiException( "Transaction ID is required" );
		}

		$requestBody = [];
		if ( !empty( $params['amount'] ) ) {
			$requestBody['amount'] = $params['amount'];
		}

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( "Launching REST capture request for Gravy transaction with ID: {$params['gateway_txn_id']} and parameters {$requestBody}" );

		$response = $this->GravyApiClient->captureTransaction( $trxn_id, $requestBody );
		return $response;
	}
}
