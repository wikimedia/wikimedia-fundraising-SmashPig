<?php
namespace SmashPig\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\Configuration;

use PHPUnit_Framework_TestCase;

class BaseSmashPigUnitTestCase extends PHPUnit_Framework_TestCase {

	function setUp() {
		parent::setUp();

		require_once __DIR__ . '/../vendor/autoload.php';

		// Disable normal config search paths.
		TestingConfiguration::installTestConfiguration();
	}

	function tearDown() {
		Context::set(); // Nullify the context for next run.
		TestingConfiguration::tearDownConfiguration(); // And the default config
	}

	function loadJson( $path ) {
		return json_decode( file_get_contents( $path ), true );
	}

	/**
	 * Set a test configuration and initialize the context
	 *
	 * @param string $configNode node to use for configuration overrides
	 * @param string $configPath path to configuration override file
	 * @return Configuration
	 */
	function setConfig( $configNode = 'default', $configPath = null ) {
		$config = Configuration::createForViewWithOverrideFile( $configNode, $configPath );
		Context::initWithLogger( $config );
		return $config;
	}
}
