<?php

namespace SmashPig\PaymentProviders\Braintree\Tests;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Braintree\VenmoPaymentProvider;

/**
 * @group Braintree
 */
class VenmoPaymentProviderTest extends BaseBraintreeTest {

	public function setUp(): void {
		parent::setUp();
	}

	public function testVenmoPaymentWithNoDeviceDataError() {
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"currency" => "USD"
		];

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'device_data' );
	}

	public function testVenmoNotUSDError() {
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"device_data" => '{}',
			"currency" => "EUR"
		];

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'currency' );
	}

	public function testAuthorizePaymentVenmo() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";

		$customer = [
			'venmoUserId' => '12345',
			'venmoUserName' => 'venmojoe',
			'firstName' => 'Jimmy',
			'lastName' => 'Wales',
			'phoneNumber' => '131313131',
			'email' => 'mockjwales@wikimedia.org',
		];
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"device_data" => '{}',
			'first_name' => $customer['firstName'],
			'last_name' => $customer['lastName'],
			'email' => $customer['email'],
			'phone' => $customer['phoneNumber'],
			"currency" => 'USD'
		];
		$this->api->expects( $this->once() )
			->method( 'authorizePaymentMethod' )
			->willReturn( [
				'data' => [
					'authorizePaymentMethod' => [

						'transaction' => [
							'id' => $txn_id,
							'status' => "AUTHORIZED",
							'customer' => [
								'id' => $customer['venmoUserId'],
								'lastName' => $customer['lastName'],
								'firstName' => $customer['firstName'],
								'email' => $customer['email'],
								'phoneNumber' => $customer['phoneNumber'],
							],
							'paymentMethodSnapshot' => [
								'username' => $customer['venmoUserName'],
								'venmoUserId' => $customer['venmoUserId'],
							]
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$donor_details = $response->getDonorDetails();
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
		$this->assertEquals( $customer[ 'firstName' ], $donor_details->getFirstName() );
		$this->assertEquals( $customer[ 'lastName' ], $donor_details->getLastName() );
		$this->assertEquals( $customer[ 'email' ], $donor_details->getEmail() );
		$this->assertEquals( $customer[ 'phoneNumber' ], $donor_details->getPhone() );
		$this->assertEquals( $customer['venmoUserId'], $donor_details->getCustomerId() );
		$this->assertEquals( $customer['venmoUserName'], $donor_details->getUserName() );
	}

	public function testApprovePayment() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";
		$request = [
			"gateway_txn_id" => $txn_id,
		];
		$this->api->expects( $this->once() )
			->method( 'captureTransaction' )
			->willReturn( [
				'data' => [
					'captureTransaction' => [
						'transaction' => [
							'id' => $txn_id,
							'status' => "SUBMITTED_FOR_SETTLEMENT",
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );

		$provider = new VenmoPaymentProvider();
		$response = $provider->approvePayment( $request );
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
	}

	public function testRemoveRecurringPaymentToken() {
		$provider = new VenmoPaymentProvider();
		$params = [
			"email" => 'joe@example.com',
			"recurring_payment_token" => '111adsfasdfa23qaser32',
			"processor_contact_id" => '12342134',
			"order_id" => '123.3',
		];
		$this->api->expects( $this->once() )
			->method( 'deletePaymentMethodFromVault' )
			->willReturn( [
				'data' => [
					'deletePaymentMethodFromVault' => [
						'clientMutationId' => $params['recurring_payment_token']
					]
				]
			] );
		$this->api->expects( $this->once() )
			->method( 'deleteCustomer' )
			->willReturn( [
				'data' => [
					'deleteCustomer' => [
						'clientMutationId' => $params['processor_contact_id']
					]
				]
			] );
		$response = $provider->deleteRecurringPaymentToken( $params );
		$this->assertTrue( $response );
	}
}
