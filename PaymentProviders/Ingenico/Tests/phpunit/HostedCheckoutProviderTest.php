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

	public function setUp(){
		parent::setUp();
		$this->setProviderConfiguration( 'ingenico' );
		$this->provider = new HostedCheckoutProvider(array('subdomain' => 'payments.test'));
	}

	public function testCreateHostedPayment() {
		$params = array(
			array(
			"hostedCheckoutSpecificInput" => array(
				"locale" => "en_GB",
				"variant" => "testVariant"
				),
			),
			"order" => array(
				"amountOfMoney" => array(
					"currencyCode" => "USD",
					"amount" => 2345
				),
				"customer" => array(
					"billingAddress" => array(
						"countryCode" => "US"
					)
				)
			)
		);
		$expectedResponse = array(
			"partialRedirectUrl" => "poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0",
			"hostedCheckoutId" => "8915-28e5b79c889641c8ba770f1ba576c1fe",
			"RETURNMAC" => "f5b66cf9-c64c-4c8d-8171-b47205c89a56"
		);
		$this->setUpResponse(__Dir__ . '/../Data/newHostedCheckout.response', 200);
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo('https://api-sandbox.globalcollect.com/v1/1234/hostedcheckouts'),
				$this->equalTo('POST')
			);
		$response = $this->provider->createHostedPayment($params);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testGetHostedPaymentUrl() {
		$partialRedirectUrl = "poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe";
		$hostedPaymentUrl = $this->provider->getHostedPaymentUrl($partialRedirectUrl);
		$expectedUrl = 'https://payments.test.' . $partialRedirectUrl;
		$this->assertEquals($expectedUrl, $hostedPaymentUrl);
	}
}
