<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\PaymentProviders\Gravy\Mapper\ErrorMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class CardPaymentProviderTest extends BaseGravyTestCase {

	/**
	 * @var CardPaymentProvider
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/cc' );
	}

	public function testSuccessfulCreatePayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $responseBody );

		$params = $this->getCreateTrxnParams( $responseBody['checkout_session_id'], $responseBody['amount'] );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testErrorCreatePayment() {
		$apiErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-api-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $apiErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $apiErrorResponseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testDupErrorCreatePayment() {
		$dupTrxnErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-duplicate-transaction-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $dupTrxnErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $dupTrxnErrorResponseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testFailedRequestErrorCreatePayment() {
		$requestErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-request-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $requestErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $requestErrorResponseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testValidationErrorCreatePayment() {
		$validationErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-validation-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $validationErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $validationErrorResponseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testSuccessfulCreateSession() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/new-checkout-session-response.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentSession' )
			->willReturn( $responseBody );

		$response = $this->provider->createPaymentSession();

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $responseBody['id'], $response->getPaymentSession() );
		$this->assertEquals( $responseBody, $response->getRawResponse() );
	}

	public function testErrorCreatePaymentSession() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/new-checkout-session-fail-response.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentSession' )
			->willReturn( $responseBody );

		$response = $this->provider->createPaymentSession();

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( $responseBody, $response->getRawResponse() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $responseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	private function getCreateTrxnParams( string $checkoutSessionId, ?string $amount = '1299' ) {
		$params = [];
		$params['country'] = 'US';
		$params['currency'] = 'USD';
		$params['amount'] = $amount;
		$params['gateway_session_id'] = $checkoutSessionId;
		$ct_id = mt_rand( 100000, 1000009 );
		$params['order_id'] = "$ct_id.1";

		return $params;
	}
}
