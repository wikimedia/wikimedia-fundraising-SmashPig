<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use PHPUnit_Framework_MockObject_MockObject;
use SmashPig\PaymentProviders\Ingenico\ApiException;
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
				$this->equalTo( 'https://api-sandbox.globalcollect.com/v1/1234/hostedcheckouts' ),
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
				$this->equalTo( "https://api-sandbox.globalcollect.com/v1/1234/hostedcheckouts/$hostedPaymentId" ),
				$this->equalTo( 'GET' )
			);
		$response = $this->provider->getHostedPaymentStatus( $hostedPaymentId );
		$this->assertEquals( 'PAYMENT_CREATED', $response['status'] );
	}

	/**
	 * this shouild test that the ApiException is thrown but the message contents is subject to implementation
	 * @requires PHPUnit 5
	 * @see  mediawiki-fr/extensions/DonationInterface/vendor/wikimedia/smash-pig/PaymentProviders/Ingenico/Api.php:86
	 */
	public function testGetHostedPaymentStatusRejectedThrowsAPIException() {
		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( "Error code 430285: Not authorised." ); // need to work this out once implemented?

		$hostedPaymentId = 'DUMMY-ID-8915-28e5b79c889641c8ba770f1ba576c1fe';
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatusRejected.response", 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo( "https://api-sandbox.globalcollect.com/v1/1234/hostedcheckouts/$hostedPaymentId" ),
				$this->equalTo( 'GET' )
			);

		$response = $this->provider->getHostedPaymentStatus( $hostedPaymentId );
	}

}
