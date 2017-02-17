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

	/**
	 * @var BankPaymentProvider
	 */
	protected $provider;

	public function setUp() {
		$config = $this->setConfig( 'ingenico' );
		$this->curlWrapper = $this->getMock( '\SmashPig\Core\Http\CurlWrapper' );
		$config->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		$config->object( 'cache' )->clear();
		$this->provider = new BankPaymentProvider( array(
			'cache-parameters' => array(
				'duration' => 10,
				'key-base' => 'BLAH_BLAH'
			)
		) );
		parent::setUp();
	}

	public function testGetBankList() {
		$this->setUpResponse( 'productDirectory', 200 );
		$results = $this->provider->getBankList( 'NL', 'EUR' );
		$this->assertEquals(
			array(
				'INGBNL2A' => 'Issuer Simulation V3 - ING'
			),
			$results
		);
	}

	public function testCacheBankList() {
		$this->setUpResponse( 'productDirectory', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' );
		$results = $this->provider->getBankList( 'NL', 'EUR' );
		$this->assertEquals(
			array(
				'INGBNL2A' => 'Issuer Simulation V3 - ING'
			),
			$results
		);
		$cachedResults = $this->provider->getBankList( 'NL', 'EUR' );
		$this->assertEquals( $results, $cachedResults );
	}

	protected function setUpResponse( $filename, $statusCode ) {
		$contents = file_get_contents( __DIR__ . "/../Data/$filename.response" );
		$parsed = CurlWrapper::parseResponse(
			$contents, array( 'http_code' => $statusCode )
		);
		$this->curlWrapper->method( 'execute' )->willReturn( $parsed );
	}
}