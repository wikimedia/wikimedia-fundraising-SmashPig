<?php
namespace SmashPig\PaymentProviders\Ingenico\Tests;

use PHPUnit_Framework_MockObject_MockObject;
use SmashPig\Core\Http\CurlWrapper;
use SmashPig\PaymentProviders\Ingenico\BankPaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class BankPaymentProviderTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $curlWrapper;

	public function setUp() {
		$config = $this->setConfig( 'ingenico' );
		$this->curlWrapper = $this->getMock( '\SmashPig\Core\Http\CurlWrapper' );
		$config->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		parent::setUp();
	}

	public function testGetBankList() {
		$this->setUpResponse( 'productDirectory', 200 );
		$provider = new BankPaymentProvider();
		$results = $provider->getBankList( 'NL', 'EUR' );
		$this->assertEquals(
			array(
				'INGBNL2A' => 'Issuer Simulation V3 - ING'
			),
			$results
		);
	}

	protected function setUpResponse( $filename, $statusCode ) {
		$contents = file_get_contents( __DIR__ . "/../Data/$filename.response" );
		$parsed = CurlWrapper::parseResponse(
			$contents, array( 'http_code' => $statusCode )
		);
		$this->curlWrapper->method( 'execute' )->willReturn( $parsed );
	}
}
