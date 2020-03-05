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

	/**
	 * @param $refusalReason
	 * @dataProvider cannotRetryRefusalReasons
	 */
	public function testNonRetryableFailedRecurringCreatePaymentCall( $refusalReason ) {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( (object)[ 'paymentResult' => (object)[
				'resultCode' => 'Refused',
				'refusalReason' => $refusalReason,
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
		$this->assertEquals( $refusalReason, $firstError->getDebugMessage() );
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
	}

	/**
	 * @param $refusalReason
	 * @dataProvider canRetryRefusalReasons
	 */
	public function testRetryableFailedRecurringCreatePaymentCall( $refusalReason ) {
		$mockApi = $this->createMock( 'SmashPig\PaymentProviders\Adyen\Api' );
		$mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( (object)[ 'paymentResult' => (object)[
				'resultCode' => 'Refused',
				'refusalReason' => $refusalReason,
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
		$this->assertEquals( ErrorCode::DECLINED, $firstError->getErrorCode() );
		$this->assertEquals( $refusalReason, $firstError->getDebugMessage() );
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
	}

	/**
	 * Refusal codes taken from https://docs.adyen.com/development-resources/test-cards/result-code-testing/adyen-response-codes
	 * @return array
	 */
	public function canRetryRefusalReasons() {
		return [
			[ 'Unknown' ],
			[ 'Refused' ],
			[ 'Acquirer Error' ],
			[ 'Expired Card' ],
			[ 'Issuer Unavailable' ],
			[ 'Not supported' ],
			[ '3D Not Authenticated' ],
			[ 'Not enough balance' ],
			[ 'Pending' ],
			[ 'Cancelled' ],
			[ 'Shopper Cancelled' ],
			[ 'Pin tries exceeded' ],
			[ 'Not Submitted' ],
			[ 'Transaction Not Permitted' ],
			[ 'CVC Declined' ],
			[ 'Declined Non Generic' ],
			[ 'Withdrawal amount exceeded' ],
			[ 'Withdrawal count exceeded' ],
			[ 'Amount partially approved' ],
			[ 'AVS Declined' ],
			[ 'Card requires online pin' ],
			[ 'No checking account available on Card' ],
			[ 'No savings account available on Card' ],
			[ 'Mobile PIN required' ],
			[ 'Contactless fallback' ],
			[ 'Authentication required' ]
		];
	}

	/**
	 * Refusal codes taken from https://docs.adyen.com/development-resources/test-cards/result-code-testing/adyen-response-codes
	 * @return array
	 */
	public function cannotRetryRefusalReasons() {
		return [
			[ 'Acquirer Fraud' ],
			[ 'Blocked Card' ],
			[ 'FRAUD' ],
			[ 'FRAUD-CANCELLED' ],
			[ 'Invalid Amount' ],
			[ 'Invalid Card Number' ],
			[ 'Invalid Pin' ],
			[ 'No Contract Found' ],
			[ 'Pin validation not possible' ],
			[ 'Referral' ],
			[ 'Restricted Card' ],
			[ 'Revocation Of Auth' ],
			[ 'Issuer Suspected Fraud' ]
		];
	}
}
