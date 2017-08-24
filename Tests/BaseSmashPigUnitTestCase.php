<?php
namespace SmashPig\Tests;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use SmashPig\Core\Context;
use SmashPig\Core\Http\CurlWrapper;

class BaseSmashPigUnitTestCase extends PHPUnit_Framework_TestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $curlWrapper;

	public function setUp() {
		parent::setUp();
		$globalConfig = TestingGlobalConfiguration::create();
		TestingContext::init( $globalConfig );
		$this->curlWrapper = $this->getMock( '\SmashPig\Core\Http\CurlWrapper' );
	}

	public function tearDown() {
		Context::set(); // Nullify the context for next run.
	}

	/**
	 * @param string $filepath Full path to file representing a
	 *  response (headers, blank line, body), which must use dos-style
	 *  \r\n line endings.
	 * @param int $statusCode
	 */
	protected function setUpResponse( $filepath, $statusCode ) {
		$contents = file_get_contents( $filepath );
		$header_size = strpos( $contents, "\r\n\r\n" ) + 4;
		$parsed = CurlWrapper::parseResponse(
			$contents, array( 'http_code' => $statusCode, 'header_size' => $header_size )
		);
		$this->curlWrapper->method( 'execute' )->willReturn( $parsed );
	}

	protected function loadJson( $path ) {
		return json_decode( file_get_contents( $path ), true );
	}

	/**
	 * @param $provider
	 * @return TestingProviderConfiguration
	 */
	protected function setProviderConfiguration( $provider ) {
		$ctx = Context::get();
		$globalConfig = $ctx->getGlobalConfiguration();
		$config = TestingProviderConfiguration::createForProvider( $provider, $globalConfig );
		$ctx->setProviderConfiguration( $config );
		$config->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		return $config;
	}
}
