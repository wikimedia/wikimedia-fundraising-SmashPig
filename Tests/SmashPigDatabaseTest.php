<?php

namespace SmashPig\Tests;


use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\DataStores\PendingDatabase;

class SmashPigDatabaseTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PendingDatabase
	 */
	protected $pendingDb;

	/**
	 * @var PaymentsInitialDatabase
	 */
	protected $paymentsInitialDb;

	public function setUp() {
		parent::setUp();

		$this->pendingDb = PendingDatabase::get();
		$this->pendingDb->createTable();
		$this->paymentsInitialDb = PaymentsInitialDatabase::get();
		$this->paymentsInitialDb->createTable();
	}

	public function tearDown() {
		TestingDatabase::clearStatics( $this->paymentsInitialDb );
		TestingDatabase::clearStatics( $this->pendingDb );

		parent::tearDown();
	}

	public function testDifferentDatabases() {
		$pendingPdo = $this->pendingDb->getDatabase();
		$initPdo = $this->paymentsInitialDb->getDatabase();
		$this->assertNotEquals(
			spl_object_hash( $pendingPdo ),
			spl_object_hash( $initPdo ),
			'Pending and paymentsInit databases share the same PDO'
		);
	}
}
