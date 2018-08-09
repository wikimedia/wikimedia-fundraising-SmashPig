<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class HostedCheckoutProviderTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var HostedCheckoutProvider
	 */
	protected $provider;

	public function setUp() {
		parent::setUp();
		$this->setProviderConfiguration( 'ingenico' );
		$this->provider = new HostedCheckoutProvider( [ 'subdomain' => 'payments.test' ] );
	}

	public function testCreateHostedPayment() {
		$params = [
			[
			"hostedCheckoutSpecificInput" => [
				"locale" => "en_GB",
				"variant" => "testVariant"
				],
			],
			"order" => [
				"amountOfMoney" => [
					"currencyCode" => "USD",
					"amount" => 2345
				],
				"customer" => [
					"billingAddress" => [
						"countryCode" => "US"
					]
				]
			]
		];
		$expectedResponse = [
			"partialRedirectUrl" => "poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0",
			"hostedCheckoutId" => "8915-28e5b79c889641c8ba770f1ba576c1fe",
			"RETURNMAC" => "f5b66cf9-c64c-4c8d-8171-b47205c89a56"
		];
		$this->setUpResponse( __Dir__ . '/../Data/newHostedCheckout.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( 'https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts' ),
				$this->equalTo( 'POST' )
			);
		$response = $this->provider->createHostedPayment( $params );
		$this->assertEquals( $expectedResponse, $response );
	}

	public function testGetHostedPaymentUrl() {
		$partialRedirectUrl = "poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe";
		$hostedPaymentUrl = $this->provider->getHostedPaymentUrl( $partialRedirectUrl );
		$expectedUrl = 'https://payments.test.' . $partialRedirectUrl;
		$this->assertEquals( $expectedUrl, $hostedPaymentUrl );
	}

	public function testGetHostedPaymentStatus() {
		$hostedPaymentId = '8915-28e5b79c889641c8ba770f1ba576c1fe';
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatus.response", 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts/$hostedPaymentId" ),
				$this->equalTo( 'GET' )
			);
		$response = $this->provider->getHostedPaymentStatus( $hostedPaymentId );
		$this->assertEquals( 'PAYMENT_CREATED', $response['status'] );
	}

	/**
	 * @dataProvider hostedPaymentStatusRejectedErrors
	 */
	public function testGetHostedPaymentStatusFailuresReturnErrors( $errorCode, $errorDescription ) {
		$hostedPaymentId = 'DUMMY-ID-8915-28e5b79c889641c8ba770f1ba576c1fe';
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatusRejected$errorCode.response", 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts/$hostedPaymentId" ),
				$this->equalTo( 'GET' )
			);

		$response = $this->provider->getHostedPaymentStatus( $hostedPaymentId );
		$this->assertNotEmpty( $response['errors'] );
		$this->assertEquals( $errorCode, $response['errors'][0]['code'] );
		$this->assertEquals( $errorDescription, $response['errors'][0]['message'] );
	}

	/**
	 * We don't have an exhaustive list here; the codes below are the failure event
	 * codes that we've been able to evoke so far using the Ingenico test card details
	 */
	public function hostedPaymentStatusRejectedErrors() {
		return [
			[ '430424', 'Unable to authorise' ],
			[ '430475', 'Not authorised' ],
			[ '430327', 'Unable to authorise' ],
			[ '430409', 'Referred' ],
			[ '430330', 'Not authorised' ],
			[ '430306', 'Card expired' ],
			[ '430260', 'Not authorised' ],
		];
	}

	public function testGetHostedPaymentStatusInProgress() {
		$hostedPaymentId = '8915-28e5b79c889641c8ba770f1ba576c1fe';
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatusIN_PROGRESS.response", 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts/$hostedPaymentId" ),
				$this->equalTo( 'GET' )
			);
		$response = $this->provider->getHostedPaymentStatus( $hostedPaymentId );
		$this->assertEquals( 'IN_PROGRESS', $response['status'] );
	}

}
