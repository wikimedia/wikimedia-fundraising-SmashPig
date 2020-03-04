<?php

namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Context;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\Adyen\PaymentProvider;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;

/**
 * @group Adyen
 * @group Recurring
 */
class RecurringPaymentTest extends BaseAdyenTestCase {

	/**
	 * @var PaymentProvider
	 */
	public $provider;

	public function setUp() {
		parent::setUp();
		$ctx = Context::get();
		$this->config = AdyenTestConfiguration::instance( [], $ctx->getGlobalConfiguration() );
		$this->provider = new PaymentProvider();
		$ctx->setProviderConfiguration( $this->config );
	}

	public function testGoodRecurringCreatePaymentCall() {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( (object)[ 'paymentResult' => (object)[
				'resultCode' => 'Authorised',
				'pspReference' => '00000000000000AB'
			]
			] );

		$reflection = new \ReflectionObject( $this->provider );
		$reflection_property = $reflection->getProperty( 'api' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $this->provider, $mockApi );

		// test params
		$params['recurring'] = true;
		$params['reference'] = "RECURRING-TEST-" . rand( 0, 10000 );
		$params['recurring_payment_token'] = 'TEST-TOKEN-123';
		$params['currency'] = 'USD';
		$params['amount'] = '9.99';

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\CreatePaymentResponse',
			$createPaymentResponse );
		$this->assertTrue( count( $createPaymentResponse->getErrors() ) == 0 );
		$this->assertTrue( $createPaymentResponse->isSuccessful() );
	}

	public function testBadRecurringCreatePaymentCall() {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( (object)[ 'paymentResult' => (object)[
				'resultCode' => 'Refused',
				'refusalReason' => '800 No Contract Found',
				'pspReference' => '00000000000000AB'
			]
			] );

		$reflection = new \ReflectionObject( $this->provider );
		$reflection_property = $reflection->getProperty( 'api' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $this->provider, $mockApi );

		// test params
		$params['recurring'] = true;
		$params['reference'] = "RECURRING-TEST-" . rand( 0, 10000 );
		$params['recurring_payment_token'] = 'TEST-TOKEN-123';
		$params['currency'] = 'USD';
		$params['amount'] = '9.99';

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\CreatePaymentResponse', $createPaymentResponse );
		$this->assertTrue( $createPaymentResponse->hasErrors() );
		$firstError = $createPaymentResponse->getErrors()[0];
		$this->assertEquals( ErrorCode::DECLINED_DO_NOT_RETRY, $firstError->getErrorCode() );
		$this->assertEquals( '800 No Contract Found', $firstError->getDebugMessage() );
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
	}
}
