<?php
namespace SmashPig\PaymentProviders\Ingenico\Tests;

use PHPUnit_Framework_MockObject_MockObject;
use Psr\Cache\CacheItemPoolInterface;
use SmashPig\Core\Cache\HashCacheItem;
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

	/**
	 * When the lookup returns 404 we should cache the emptiness
	 */
	public function testCacheEmptyBankList() {
		$this->setUpResponse( 'emptyDirectory', 404 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' );
		$results = $this->provider->getBankList( 'NL', 'COP' );
		$this->assertEquals( array(), $results );
		$cached = $this->cache->getItem( 'BLAH_BLAH_NL_COP_809' );
		$this->assertTrue( $cached->isHit() );
		$again = $this->provider->getBankList( 'NL', 'COP' );
		$this->assertEquals( $results, $again );
	}

	public function testBustedCacheExpiration() {
		$cacheItem = new HashCacheItem(
			'BLAH_BLAH_NL_EUR_809',
			array(
				'value' => array( 'STALE' => 'NotValid' ),
				'expiration' => time() - 100
			),
			true
		);
		$this->cache->save( $cacheItem );
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
	}

	/**
	 * @param string $filename Name of a file in ../Data representing a
	 *  response (headers, blank line, body), which must use dos-style
	 *  \r\n line endings.
	 * @param integer $statusCode
	 */
	protected function setUpResponse( $filename, $statusCode ) {
		$contents = file_get_contents( __DIR__ . "/../Data/$filename.response" );
		$parsed = CurlWrapper::parseResponse(
			$contents, array( 'http_code' => $statusCode )
		);
		$this->curlWrapper->method( 'execute' )->willReturn( $parsed );
	}
}
