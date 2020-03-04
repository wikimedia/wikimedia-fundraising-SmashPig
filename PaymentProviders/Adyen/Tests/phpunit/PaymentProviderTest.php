<?php

namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\Core\Context;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\Adyen\PaymentProvider;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * @group Adyen
 */
class PaymentProviderTest extends BaseAdyenTestCase {

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

	public function testGoodApprovePayment() {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( (object)[ 'captureResult' => (object)[
				'response' => '[capture-received]',
				'pspReference' => '00000000000000AB'
			]
			] );

		$reflection = new \ReflectionObject( $this->provider );
		$reflection_property = $reflection->getProperty( 'api' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $this->provider, $mockApi );

		// test params
		$params['gateway_txn_id'] = "CAPTURE-TEST-" . rand( 0, 100 );
		$params['currency'] = 'USD';
		$params['currency'] = '9.99';

		$approvePaymentResponse = $this->provider->approvePayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\ApprovePaymentResponse',
			$approvePaymentResponse );
		$this->assertEquals( '[capture-received]', $approvePaymentResponse->getRawStatus() );
		$this->assertEquals( '00000000000000AB', $approvePaymentResponse->getGatewayTxnId() );
		$this->assertTrue( $approvePaymentResponse->isSuccessful() );
		$this->assertTrue( count( $approvePaymentResponse->getErrors() ) == 0 );
	}

	/**
	 * Currently if we make an approvePayment call with an invalid payment id it triggers a
	 * SoapFault within the Api class which is then caught and false is retured
	 *
	 * @see PaymentProviders/Adyen/Api.php:101
	 *
	 */
	public function testBadApprovePayment() {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( false );

		$reflection = new \ReflectionObject( $this->provider );
		$reflection_property = $reflection->getProperty( 'api' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $this->provider, $mockApi );

		// test params
		$params['gateway_txn_id'] = "INVALID-ID-0000";
		$params['currency'] = 'USD';
		$params['currency'] = '9.99';

		$approvePaymentResponse = $this->provider->approvePayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\ApprovePaymentResponse',
			$approvePaymentResponse );
		$this->assertFalse( $approvePaymentResponse->isSuccessful() );
		$this->assertTrue( $approvePaymentResponse->hasErrors() );
		$firstError = $approvePaymentResponse->getErrors()[0];
		$this->assertEquals( ErrorCode::MISSING_REQUIRED_DATA, $firstError->getErrorCode() );
		$this->assertEquals(
			'captureResult element missing from Adyen approvePayment response.',
			$firstError->getDebugMessage()
		);
	}

	public function testUnknownStatusReturnedForApprovePayment() {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( (object)[ 'captureResult' => (object)[
				'response' => '[unknown-status]',
				'pspReference' => '00000000000000AB'
			]
			] );

		$reflection = new \ReflectionObject( $this->provider );
		$reflection_property = $reflection->getProperty( 'api' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $this->provider, $mockApi );

		// test params
		$params['gateway_txn_id'] = "CAPTURE-TEST-" . rand( 0, 100 );
		$params['currency'] = 'USD';
		$params['currency'] = '9.99';

		$approvePaymentResponse = $this->provider->approvePayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\ApprovePaymentResponse',
			$approvePaymentResponse );
		$this->assertFalse( $approvePaymentResponse->isSuccessful() );
		$this->assertTrue( $approvePaymentResponse->hasErrors() );
		$firstError = $approvePaymentResponse->getErrors()[0];
		$this->assertInstanceOf( 'SmashPig\Core\PaymentError', $firstError );
		$this->assertEquals( ErrorCode::UNEXPECTED_VALUE, $firstError->getErrorCode() );
		$this->assertEquals(
			'Unknown Adyen status [unknown-status]',
			$firstError->getDebugMessage()
		);
	}

	public function testGoodCancelPayment() {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'cancel' )
			->willReturn( (object)[ 'cancelResult' => (object)[
				'response' => '[cancel-received]',
				'pspReference' => '00000000000000AB'
			]
			] );

		$reflection = new \ReflectionObject( $this->provider );
		$reflection_property = $reflection->getProperty( 'api' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $this->provider, $mockApi );

		// test params
		$params['gateway_txn_id'] = "CANCEL-TEST-" . rand( 0, 100 );
		$params['currency'] = 'USD';
		$params['currency'] = '9.99';

		$cancelPaymentResponse = $this->provider->cancel( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\CancelPaymentResponse',
			$cancelPaymentResponse );
		$this->assertEquals( '[cancel-received]', $cancelPaymentResponse->getRawStatus() );
		$this->assertEquals( '00000000000000AB', $cancelPaymentResponse->getGatewayTxnId() );
		$this->assertTrue( $cancelPaymentResponse->isSuccessful() );
		$this->assertTrue( count( $cancelPaymentResponse->getErrors() ) == 0 );
	}

	/**
	 * Currently if we make a cancel call with an invalid payment id it triggers a
	 * SoapFault within the Api class which is then caught and false is returned
	 *
	 * @see PaymentProviders/Adyen/Api.php:125
	 *
	 */
	public function testBadCancelPayment() {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'cancel' )
			->willReturn( false );

		$reflection = new \ReflectionObject( $this->provider );
		$reflection_property = $reflection->getProperty( 'api' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $this->provider, $mockApi );

		// test params
		$params['gateway_txn_id'] = "INVALID-ID-0000";
		$params['currency'] = 'USD';
		$params['currency'] = '9.99';

		$cancelPaymentResponse = $this->provider->cancel( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\CancelPaymentResponse',
			$cancelPaymentResponse );
		$this->assertFalse( $cancelPaymentResponse->isSuccessful() );

		$firstError = $cancelPaymentResponse->getErrors()[0];
		$this->assertInstanceOf( 'SmashPig\Core\PaymentError', $firstError );
		$this->assertEquals( ErrorCode::MISSING_REQUIRED_DATA, $firstError->getErrorCode() );
		$this->assertEquals(
			'cancelResult element missing from Adyen cancel response.',
			$firstError->getDebugMessage()
		);
	}
}
