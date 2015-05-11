<?php

class BaseSmashPigUnitTestCase extends PHPUnit_Framework_TestCase {
	function setUp() {
		parent::setUp();

		require_once __DIR__ . '/../vendor/autoload.php';
	}

	function loadJson( $path ) {
		return json_decode( file_get_contents( $path ), true );
	}
}
