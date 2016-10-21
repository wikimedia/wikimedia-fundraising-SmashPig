<?php

namespace SmashPig\Tests;

class TestingDatabase {
	/**
	 * @param string|object $classish Database class to reset, as a qualified
	 * class name or example object.  Must be a subtype of SmashPigDatabase for
	 * the statics to make sense.
	 */
	public static function clearStatics( $classish ) {
		$klass = new \ReflectionClass( $classish );
		$dbProperty = $klass->getProperty( 'dbs' );
		$dbProperty->setAccessible( true );
		$dbProperty->setValue( array() );
	}
}
