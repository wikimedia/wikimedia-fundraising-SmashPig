<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\CardPaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class CardPaymentProviderTest extends BaseSmashPigUnitTestCase {

	protected $api;

	public function setUp() : void {
		parent::setUp();
		$providerConfig = $this->setProviderConfiguration( 'dlocal' );
		$this->api = $this->getMockBuilder( Api::class )
				->disableOriginalConstructor()
				->getMock();
		$providerConfig->overrideObjectInstance( 'api', $this->api );
	}

	public function testPaymentWithIncompleteParams(): void {
		$request = [
				"payment_token" => "fake-token",
				"order_id" => '123.3',
				"amount" => '1.00',
				"currency" => "USD"
		];

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertTrue( count( $validationError ) > 0 );
		$this->assertFalse( $response->isSuccessful() );
	}

	public function testPaymentWithCompleteParamsSuccess(): void {
		$params = $this->getCreatePaymentRequestParams();
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
			->method( 'authorizePayment' )
			->with( $params )
			->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
							"holder_name" => "Lorem Ipsum",
							"expiration_month" => 10,
							"expiration_year" => 2040,
							"last4" => "1111",
							"brand" => "VI"
						],
						  "created_date" => "2018-02-15T15:14:52-00:00",
						  "approved_date" => "2018-02-15T15:14:52-00:00",
						  "status" => "AUTHORIZED",
						  "status_code" => "200",
						  "status_detail" => "The payment was paid.",
						  "order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$validationError = $response->getValidationErrors();
		$this->assertCount( 0, $validationError );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
	}

	public function testPaymentWithCompleteParamsFail(): void {
		$params = $this->getCreatePaymentRequestParams();
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
			->method( 'authorizePayment' )
			->with( $params )
			->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
								"holder_name" => "Lorem Ipsum",
								"expiration_month" => 10,
								"expiration_year" => 2040,
								"last4" => "1111",
								"brand" => "VI"
						],
						"created_date" => "2018-02-15T15:14:52-00:00",
						"approved_date" => "2018-02-15T15:14:52-00:00",
						"status" => "REJECTED",
						"status_code" => "300",
						"status_detail" => "The payment was rejected",
						"order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
	}

	public function testPaymentWithCompleteParamsPending(): void {
		$params = $this->getCreatePaymentRequestParams();
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
			->method( 'authorizePayment' )
			->with( $params )
			->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
								"holder_name" => "Lorem Ipsum",
								"expiration_month" => 10,
								"expiration_year" => 2040,
								"last4" => "1111",
								"brand" => "VI"
						],
						"created_date" => "2018-02-15T15:14:52-00:00",
						"approved_date" => "2018-02-15T15:14:52-00:00",
						"status" => "AUTHORIZED",
						"status_code" => "100",
						"status_detail" => "The payment is pending.",
						"order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 0, $error );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
	}

public function testPaymentWithCompleteParamsFailsDueToUnknownStatus(): void {
		$params = $this->getCreatePaymentRequestParams();
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
				->method( 'authorizePayment' )
				->with( $params )
				->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
								"holder_name" => "Lorem Ipsum",
								"expiration_month" => 10,
								"expiration_year" => 2040,
								"last4" => "1111",
								"brand" => "VI"
						],
						"created_date" => "2018-02-15T15:14:52-00:00",
						"approved_date" => "2018-02-15T15:14:52-00:00",
						"status" => "UNKNOWN",
						"status_code" => "300",
						"status_detail" => "The payment was rejected.",
						"order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::UNKNOWN, $response->getStatus() );
}

	public function testPaymentWithCompleteParamsFailsAndEmptyStatusInResponse(): void {
		$params = $this->getCreatePaymentRequestParams();
		$this->api->expects( $this->once() )
				->method( 'authorizePayment' )
				->with( $params )
				->willReturn( [
						"code" => 5008,
						"message" => "Token not found or inactive"
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::UNKNOWN, $response->getStatus() );
	}

	public function testApprovePaymentSuccess(): void {
		$params = [
			"gateway_txn_id" => "T-2486-91e73695-3e0a-4a77-8594-f2220f8c6515",
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$this->api->expects( $this->once() )
			->method( 'capturePayment' )
			->with( $params )
			->willReturn( [
				'id' => 'T-2486-aa9c1884-9f54-409a-9223-ede614b78173',
				'amount' => 100,
				'currency' => 'BRL',
				'country' => 'BR',
				'status' => 'PAID',
				'status_detail' => 'The payment was paid.',
				'status_code' => '200',
				'order_id' => '1234512345',
				'notification_url' => 'http://merchant.com/notifications',
				'authorization_id' => 'T-2486-91e73695-3e0a-4a77-8594-f2220f8c6515',
		] );

		$cardPaymentProvider = new CardPaymentProvider();
		$approvePaymentResponse = $cardPaymentProvider->approvePayment( $params );
		$this->assertTrue( $approvePaymentResponse->isSuccessful() );
		$this->assertEquals( FinalStatus::COMPLETE, $approvePaymentResponse->getStatus() );
	}

	public function testPaymentWithCompleteParamsPendingRecurringSetToTrue(): void {
		$params = $this->getCreatePaymentRequestParams();
		$params['recurring'] = "1";
		$gateway_txn_id = "PAY2323243343543";
		$card_id = "CID-e41c183d-2657-4e82-b39a-b0069c2af657";
		$this->api->expects( $this->once() )
			->method( 'authorizePayment' )
			->with( $params )
			->willReturn( [
				"id" => $gateway_txn_id,
				"amount" => 1,
				"currency" => "ZAR",
				"country" => "SA",
				"payment_method_id" => "CARD",
				"payment_method_type" => "CARD",
				"payment_method_flow" => "DIRECT",
				"card" => [
					"holder_name" => "Lorem Ipsum",
					"expiration_month" => 10,
					"expiration_year" => 2040,
					"last4" => "1111",
					"brand" => "VI",
					"card_id" => $card_id
				],
				"created_date" => "2018-02-15T15:14:52-00:00",
				"approved_date" => "2018-02-15T15:14:52-00:00",
				"status" => "AUTHORIZED",
				"status_code" => "100",
				"status_detail" => "The payment is pending.",
				"order_id" => $params['order_id'],
			] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 0, $error );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( $response->getRecurringPaymentToken(), $card_id );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
	}

	public function testPaymentWithCompleteParamsAndRecurringPaymentToken(): void {
		$params = $this->getCreatePaymentRequestParams();
		unset( $params['payment_token'] );
		$card_id = "CID-e41c183d-2657-4e82-b39a-b0069c2af657";
		$params['recurring_payment_token'] = $card_id;
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
			->method( 'makeRecurringPayment' )
			->with( $params )
			->willReturn( [
				"id" => $gateway_txn_id,
				"amount" => 1,
				"currency" => "ZAR",
				"country" => "SA",
				"payment_method_id" => "CARD",
				"payment_method_type" => "CARD",
				"payment_method_flow" => "DIRECT",
				"card" => [
					"holder_name" => "Lorem Ipsum",
					"expiration_month" => 10,
					"expiration_year" => 2040,
					"last4" => "1111",
					"brand" => "VI",
					"card_id" => $card_id
				],
				"created_date" => "2018-02-15T15:14:52-00:00",
				"approved_date" => "2018-02-15T15:14:52-00:00",
				"status" => "PAID",
				"status_code" => "200",
				"status_detail" => "The payment was paid.",
				"order_id" => $params['order_id'],
			] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 0, $error );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( $response->getRecurringPaymentToken(), $card_id );
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
	}

	public function testApprovePaymentFailMissingGatewayTxnId(): void {
		$params = [
			// gateway_txn_id is missing
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$cardPaymentProvider = new CardPaymentProvider();
		$approvePaymentResponse = $cardPaymentProvider->approvePayment( $params );
		$this->assertFalse( $approvePaymentResponse->isSuccessful() );
		$this->assertEquals( FinalStatus::FAILED, $approvePaymentResponse->getStatus() );
		$this->assertCount( 1, $approvePaymentResponse->getValidationErrors() );
		$this->assertSame( 'gateway_txn_id', $approvePaymentResponse->getValidationErrors()[0]->getField() );
	}

	private function getCreatePaymentRequestParams(): array {
		return [
						'payment_token' => 'fake-token',
						'amount' => '1.00',
						'currency' => 'ZAR',
						'country' => 'SA',
						'payment_method' => 'CARD',
						'payment_submethod' => 'DIRECT',
						'order_id' => '1234',
						'first_name' => 'Lorem',
						'last_name' => 'Ipsum',
						'email' => 'li@mail.com',
						'fiscal_number' => '12345',
						'contact_id' => '12345',
						'state_province' => 'lore',
						'city' => 'lore',
						'postal_code' => 'lore',
						'street_address' => 'lore',
						'street_number' => 2,
						'user_ip' => '127.0.0.1'
		];
	}
}
