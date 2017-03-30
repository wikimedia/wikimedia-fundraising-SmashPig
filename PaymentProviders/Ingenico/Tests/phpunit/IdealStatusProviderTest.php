<?php
namespace SmashPig\PaymentProviders\Ingenico\Tests;

use PHPUnit_Framework_MockObject_MockObject;
use Psr\Cache\CacheItemPoolInterface;
use SmashPig\Core\Http\CurlWrapper;
use SmashPig\PaymentProviders\Ingenico\BankPaymentProvider;
use SmashPig\PaymentProviders\Ingenico\IdealStatusProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class IdealStatusProviderTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $curlWrapper;

	/**
	 * @var IdealStatusProvider
	 */
	protected $provider;

	/**
	 * @var CacheItemPoolInterface
	 */
	protected $cache;

	public function setUp() {

		$config = $this->setConfig( 'ingenico' );
		$this->curlWrapper = $this->getMock( '\SmashPig\Core\Http\CurlWrapper' );
		$this->cache = $config->object( 'cache', true );
		$config->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		$config->object( 'cache' )->clear();
		$this->provider = new IdealStatusProvider( array(
			'cache-parameters' => array(
				'duration' => 10,
				'key' => 'BLAH_BLAH'
			),
			'availability-url' => 'http://example.org/undocumented/api/GetIssuers'
		) );
		$contents = file_get_contents( __DIR__ . "/../Data/availability.response" );
		$parsed = CurlWrapper::parseResponse(
			$contents, array( 'http_code' => 200 )
		);
		$this->curlWrapper->method( 'execute' )->willReturn( $parsed );
		parent::setUp();
	}

	public function testGetBankStatus() {
		$results = $this->provider->getBankStatus();
		$this->assertEquals(
			array(
				'ABNANL2A' => array(
					'name' => 'ABN AMRO',
					'availability' => '40',
				),
				'INGBNL2A' => array(
					'name' => 'Issuer Simulation V3 - ING',
					'availability' => '100',
				)
			),
			$results
		);
	}

	public function testCacheBankStatus() {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' );
		$results = $this->provider->getBankStatus();
		$this->assertEquals(
			array(
				'ABNANL2A' => array(
					'name' => 'ABN AMRO',
					'availability' => '40',
				),
				'INGBNL2A' => array(
					'name' => 'Issuer Simulation V3 - ING',
					'availability' => '100',
				)
			),
			$results
		);
		$cachedResults = $this->provider->getBankStatus();
		$this->assertEquals( $results, $cachedResults );
	}
}
