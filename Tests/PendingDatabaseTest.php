<?php

namespace SmashPig\Tests;

use SmashPig\Core\DataStores\PendingDatabase;

class PendingDatabaseTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PendingDatabase
	 */
	protected $db;

	public function setUp() {
		parent::setUp();
		$this->setConfig( 'default', __DIR__ . '/data/config_pending_db.yaml' );
		$this->db = PendingDatabase::get();

		// Create sqlite schema
		$sql = file_get_contents( __DIR__ . '/../Schema/sqlite/001_CreatePendingTable.sqlite.sql' );
		$this->db->getDatabase()->exec( $sql );
	}

	protected static function getTestMessage( $uniq = null ) {
		if ( !$uniq ) { 
			$uniq = mt_rand();
		}
		return array(
			'gateway' => 'test',
			'gateway_txn_id' => "txn-{$uniq}",
			'order_id' => "order-{$uniq}",
			'gateway_account' => 'default',
			'date' => time(),
			'amount' => 123,
			'currency' => 'EUR',
		);
	}

	public function testStoreMessage() {
		$message = self::getTestMessage();
		$this->db->storeMessage( $message );

		// Confirm work without using the API.
		$pdo = $this->db->getDatabase();
		$result = $pdo->query( "select * from pending where gateway_txn_id = '{$message['gateway_txn_id']}'" );
		$rows = $result->fetchAll();
		$this->assertEquals( 1, count( $rows ), 'One row stored and retrieved.' );
	}
}
