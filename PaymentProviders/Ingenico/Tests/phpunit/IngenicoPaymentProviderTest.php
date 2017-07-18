<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use PHPUnit_Framework_MockObject_MockObject;
use SmashPig\PaymentProviders\Ingenico\IngenicoPaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class IngenicoPaymentProviderTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $provider;

	public function setUp() {
		parent::setUp();
		$this->setProviderConfiguration( 'ingenico' );
		$this->provider = $this->getMockForAbstractClass('\SmashPig\PaymentProviders\Ingenico\IngenicoPaymentProvider');
	}

	public function testGetPaymentStatus(){
		$paymentId = '000000850010000188180000200001';
		$this->setUpResponse(__DIR__ . '/../Data/paymentStatus.response', 200);
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->equalTo("https://api-sandbox.globalcollect.com/v1/1234/payments/$paymentId"),
				$this->equalTo('GET')
			);
		$response = $this->provider->getPaymentStatus($paymentId);
		$this->assertEquals($paymentId, $response['id']);
	}
}