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
				$this->equalTo( "https://api-sandbox.globalcollect.com/v1/1234/payments/$paymentId" ),
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
				$this->equalTo( "https://api-sandbox.globalcollect.com/v1/1234/payments/$paymentId/approve" ),
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
				$this->equalTo( "https://api-sandbox.globalcollect.com/v1/1234/payments/$paymentId/cancel" ),
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
				$this->equalTo( "https://api-sandbox.globalcollect.com/v1/1234/payments/$paymentId/tokenize" ),
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
			'descriptor' => 'Recurring donation to Wikimedia!',
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
							'descriptior' => $params['descriptor'],
							'merchantReference' => $params['order_id'],
						],
				],
		];

		$this->setUpResponse( __DIR__ . '/../Data/createPayment.response', 201 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://api-sandbox.globalcollect.com/v1/1234/payments" ),
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
}
