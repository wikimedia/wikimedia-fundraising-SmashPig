<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use PHPUnit_Framework_MockObject_MockObject;
use SmashPig\PaymentProviders\Ingenico\PaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class PaymentProviderTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $provider;

	public function setUp() {
		parent::setUp();
		$this->setProviderConfiguration( 'ingenico' );
		$this->provider = $this->getMockForAbstractClass( '\SmashPig\PaymentProviders\Ingenico\PaymentProvider' );
	}

	public function testGetPaymentStatus() {
		$paymentId = '000000850010000188180000200001';
		$this->setUpResponse( __DIR__ . '/../Data/paymentStatus.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments/$paymentId" ),
				$this->equalTo( 'GET' )
			);
		$response = $this->provider->getPaymentStatus( $paymentId );
		$this->assertEquals( $paymentId, $response['id'] );
	}

	public function testApprovePayment() {
		$paymentId = '000000850010000188180000200001';
		$params = [
			"directDebitPaymentMethodSpecificInput" => [
				"dateCollect" => Date( "Ymd" ),
			],
		];
		$this->setUpResponse( __DIR__ . '/../Data/paymentApproved.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments/$paymentId/approve" ),
				$this->equalTo( 'POST' )
			);
		$response = $this->provider->approvePayment( $paymentId, $params );
		$this->assertEquals( $paymentId, $response['payment']['id'] );
	}

	public function testCancelPayment() {
		$paymentId = '000000850010000188180000200001';
		$this->setUpResponse( __DIR__ . '/../Data/paymentCanceled.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments/$paymentId/cancel" ),
				$this->equalTo( 'POST' )
			);
		$response = $this->provider->cancelPayment( $paymentId );
		$this->assertEquals( $paymentId, $response['payment']['id'] );
	}

	public function testTokenizePayment() {
		$paymentId = '000000850010000188180000200001';
		$this->setUpResponse( __DIR__ . '/../Data/paymentToken.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments/$paymentId/tokenize" ),
				$this->equalTo( 'POST' )
			);
		$response = $this->provider->tokenizePayment( $paymentId );
		$this->assertEquals(
			'bfa8a7e4-4530-455a-858d-204ba2afb77e',
			$response['token']
		);
	}

	public function testCreatePayment() {
		$params = [
			'recurring' => true,
			'installment' => 'recurring',
			'recurring_payment_token' => '229a1d6e-1b26-4c91-8e00-969a49c9d041',
			'amount' => 10, // dollars
			'currency' => 'USD',
			'description' => 'Recurring donation to Wikimedia!',
			'order_id' => '12345.1',
		];

		$expectedTransformedParams = [
			'cardPaymentMethodSpecificInput' =>
				[
					'isRecurring' => $params['recurring'],
					'recurringPaymentSequenceIndicator' => $params['installment'],
					'token' => $params['recurring_payment_token'],
				],
			'order' =>
				[
					'amountOfMoney' =>
						[
							'amount' => 1000, // cents due to AmountToCents Transformer
							'currencyCode' => $params['currency'],
						],
					'references' =>
						[
							'descriptor' => $params['description'],
							'merchantReference' => $params['order_id'],
						],
				],
		];

		$this->setUpResponse( __DIR__ . '/../Data/createPayment.response', 201 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments" ),
				$this->equalTo( 'POST' ),
				$this->anything(),
				$this->callback( function ( $arg ) use ( $expectedTransformedParams ) {
					$this->assertEquals(
						$expectedTransformedParams, json_decode( $arg, true )
					);
					return true;
				} )
			);
		$response = $this->provider->createPayment( $params );
		$this->assertEquals(
			'000000850010000188130000200001',
			$response['payment']['id']
		);
	}

	public function testCreatePaymentWithContactDetails() {
		$params = [
			'recurring' => true,
			'installment' => 'recurring',
			'recurring_payment_token' => '229a1d6e-1b26-4c91-8e00-969a49c9d041',
			'amount' => 10, // dollars
			'currency' => 'USD',
			'description' => 'Recurring donation to Wikimedia!',
			'order_id' => '12345.1',
			'first_name' => 'Testy',
			'last_name' => 'McTesterson',
			'email' => 'nobody@wikimedia.org',
			'user_ip' => '11.22.33.44',
		];

		$expectedTransformedParams = [
			'cardPaymentMethodSpecificInput' =>
				[
					'isRecurring' => $params['recurring'],
					'recurringPaymentSequenceIndicator' => $params['installment'],
					'token' => $params['recurring_payment_token'],
				],
			'order' =>
				[
					'amountOfMoney' =>
						[
							'amount' => 1000, // cents due to AmountToCents Transformer
							'currencyCode' => $params['currency'],
						],
					'references' =>
						[
							'descriptor' => $params['description'],
							'merchantReference' => $params['order_id'],
						],
					'customer' =>
						[
							'contactDetails' =>
								[
									'emailAddress' => 'nobody@wikimedia.org'
								],
							'personalInformation' =>
								[
									'name' =>
										[
											'firstName' => 'Testy',
											'surname' => 'McTesterson',
										],
								],
						],
				],
			'fraudFields' => [
				'customerIpAddress' => '11.22.33.44',
			]
		];

		$this->setUpResponse( __DIR__ . '/../Data/createPayment.response', 201 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments" ),
				$this->equalTo( 'POST' ),
				$this->anything(),
				$this->callback( function ( $arg ) use ( $expectedTransformedParams ) {
					$this->assertEquals(
						$expectedTransformedParams, json_decode( $arg, true )
					);
					return true;
				} )
			);
		$response = $this->provider->createPayment( $params );
		$this->assertEquals(
			'000000850010000188130000200001',
			$response['payment']['id']
		);
	}

	public function testCreatePaymentTruncateContactDetails() {
		$params = [
			'recurring' => true,
			'installment' => 'recurring',
			'recurring_payment_token' => '229a1d6e-1b26-4c91-8e00-969a49c9d041',
			'amount' => 10, // dollars
			'currency' => 'USD',
			'description' => 'Recurring donation to Wikimedia!',
			'order_id' => '12345.1',
			'first_name' => 'SuperLongFirstNameWhenAllWeCanSendIsFifteenChars',
			'last_name' => 'McTesterson',
			'email' => 'nobody@wikimedia.org',
			'user_ip' => '11.22.33.44',
		];

		$this->setUpResponse( __DIR__ . '/../Data/createPayment.response', 201 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments" ),
				$this->equalTo( 'POST' ),
				$this->anything(),
				$this->callback( function ( $arg ) {
					$posted = json_decode( $arg, true );
					$postedName = $posted['order']['customer']['personalInformation']['name'];
					$this->assertEquals( 'SuperLongFirstN', $postedName['firstName'] );
					return true;
				} )
			);
		$this->provider->createPayment( $params );
	}

	public function testCreatePaymentTruncateMultibyte() {
		$params = [
			'recurring' => true,
			'installment' => 'recurring',
			'recurring_payment_token' => '229a1d6e-1b26-4c91-8e00-969a49c9d041',
			'amount' => 10, // dollars
			'currency' => 'USD',
			'description' => 'Recurring donation to Wikimedia!',
			'order_id' => '12345.1',
			'first_name' => 'Супердлинноеимякогдавсечтомыможемотправитьпятнадцатьбукв',
			'last_name' => 'McTesterson',
			'email' => 'nobody@wikimedia.org',
			'user_ip' => '11.22.33.44',
		];

		$this->setUpResponse( __DIR__ . '/../Data/createPayment.response', 201 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments" ),
				$this->equalTo( 'POST' ),
				$this->anything(),
				$this->callback( function ( $arg ) {
					$posted = json_decode( $arg, true );
					$postedName = $posted['order']['customer']['personalInformation']['name'];
					$this->assertEquals( 'Супердлинноеимя', $postedName['firstName'] );
					return true;
				} )
			);
		$this->provider->createPayment( $params );
	}

	public function testRefundPayment() {
		$params = [
			'amount' => 10, // dollars
			'currency' => 'USD',
			'first_name' => 'Sally',
			'last_name' => 'Ride',
			'order_id' => '12345.1',
			'country' => 'US',
		];

		$expectedTransformedParams = [
			'amountOfMoney' =>
				[
					'amount' => 1000, // cents due to AmountToCents Transformer
					'currencyCode' => $params['currency'],
				],
			'customer' =>
				[
					'address' =>
						[
							'countryCode' => $params['country'],
							'name' =>
								[
									'firstName' => $params['first_name'],
									'surname' => $params['last_name'],
								]
						]
				],
			'refundReferences' =>
				[
					'merchantReference' => $params['order_id'],
				],
		];
		$paymentId = '00000085001000006995000';
		$this->setUpResponse( __DIR__ . '/../Data/createRefund.response', 201 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/payments/$paymentId/refund" ),
				$this->equalTo( 'POST' ),
				$this->anything(),
				$this->callback( function ( $arg ) use ( $expectedTransformedParams ) {
					$this->assertEquals(
						$expectedTransformedParams, json_decode( $arg, true )
					);
					return true;
				} )
			);
		$response = $this->provider->createRefund( $paymentId, $params );
		$this->assertEquals(
			"$paymentId-300001",
			$response['id']
		);
	}

	public function testApproveRefund() {
		$refundId = '00000085001000006995000-300001';
		$this->setUpResponse( __DIR__ . '/../Data/approveRefund.response', 204 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/refunds/$refundId/approve" ),
				$this->equalTo( 'POST' )
			);
		$response = $this->provider->approveRefund( $refundId );
		$this->assertEmpty( $response );
	}
}
