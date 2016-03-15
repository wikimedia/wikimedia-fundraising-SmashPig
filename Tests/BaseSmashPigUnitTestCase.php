<?php
use SmashPig\Core\Context;
use SmashPig\Core\Configuration;
use SmashPig\Core\Logging\Logger;

class BaseSmashPigUnitTestCase extends PHPUnit_Framework_TestCase {
	protected static $loggerCreated = false; // Aaargh!

	function setUp() {
		parent::setUp();

		require_once __DIR__ . '/../vendor/autoload.php';
	}

	function tearDown() {
		Context::set(); // Nullify the context for next run.
	}

	function loadJson( $path ) {
		return json_decode( file_get_contents( $path ), true );
	}

	function setConfig( $configPath = null, $configNode = 'default' ) {
		$defaultConfig = __DIR__ . '/../config_defaults.php';
		$config = new Configuration(
			$defaultConfig,
			$configPath,
			$configNode,
			true
		);
		Context::init( $config );
		if ( !self::$loggerCreated ) {
			// Don't care which config the logger gets, let's just not explode
			Logger::init( 'test', 'debug', $config );
			self::$loggerCreated = true;
		}
	}
}
