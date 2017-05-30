<?php

namespace SmashPig\Tests;

use SmashPig\Core\DataStores\DamagedDatabase;
use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\DataStores\PendingDatabase;

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

	/**
	 * Initialize all the db tables
	 */
	public static function createTables() {
		DamagedDatabase::get()->createTable();
		PaymentsInitialDatabase::get()->createTable();
		PendingDatabase::get()->createTable();
	}
}
