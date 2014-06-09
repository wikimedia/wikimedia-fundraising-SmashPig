<?php

use SmashPig\Core\AutoLoader;

class BaseSmashPigUnitTestCase extends PHPUnit_Framework_TestCase {
	function setUp() {
		parent::setUp();

		$root = __DIR__ . '/../';
		require_once( $root . 'Core/AutoLoader.php' );
		AutoLoader::installSmashPigAutoLoader( $root );
	}

	function loadJson( $path ) {
		return json_decode( file_get_contents( $path ), true );
	}
}
