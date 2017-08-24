<?php
namespace SmashPig\PaymentProviders\Ingenico\Tests;

use Psr\Cache\CacheItemPoolInterface;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Ingenico\IdealStatusProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class IdealStatusProviderTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var IdealStatusProvider
	 */
	protected $provider;

	/**
	 * @var CacheItemPoolInterface
	 */
	protected $cache;

	public function setUp() {
		parent::setUp();

		$providerConfiguration = $this->setProviderConfiguration( 'ingenico' );
		$this->curlWrapper = $this->getMock( '\SmashPig\Core\Http\CurlWrapper' );
		$providerConfiguration->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );

		$globalConfig = Context::get()->getGlobalConfiguration();
		$this->cache = $globalConfig->object( 'cache', true );
		$this->cache->clear();

		$this->provider = new IdealStatusProvider( array(
			'cache-parameters' => array(
				'duration' => 10,
				'key' => 'BLAH_BLAH'
			),
			'availability-url' => 'http://example.org/undocumented/api/GetIssuers'
		) );
		$this->setUpResponse( __DIR__ . "/../Data/availability.response", 200 );
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
