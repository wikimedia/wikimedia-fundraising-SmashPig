<?php
namespace SmashPig\Tests;

use SmashPig\Core\Context;

use PHPUnit_Framework_TestCase;

class BaseSmashPigUnitTestCase extends PHPUnit_Framework_TestCase {

	public function setUp() {
		parent::setUp();
		$globalConfig = TestingGlobalConfiguration::create();
		Context::init( $globalConfig );
		// TODO: create tables for all dbs/queues.
		// Standard issue CurlWrapper mock would be nice too
	}

	public function tearDown() {
		Context::set(); // Nullify the context for next run.
	}

	protected function loadJson( $path ) {
		return json_decode( file_get_contents( $path ), true );
	}

	/**
	 * @param $provider
	 * @return TestingProviderConfiguration
	 */
	protected function setProviderConfiguration( $provider ) {
		$config = TestingProviderConfiguration::createForProvider( $provider );
		Context::get()->setProviderConfiguration( $config );
		return $config;
	}
}
