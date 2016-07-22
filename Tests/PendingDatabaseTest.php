<?php

namespace SmashPig\Tests;

use PDO;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PendingDatabase;

class PendingDatabaseTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PendingDatabase
	 */
	protected $db;

	public function setUp() {
		parent::setUp();
		Context::initWithLogger( new PendingDatabaseTestConfiguration() );
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
			'date' => 1468973648,
			'amount' => 123,
			'currency' => 'EUR',
		);
	}

	public function testStoreMessage() {
		$message = self::getTestMessage();
		$this->db->storeMessage( $message );

		// Confirm work without using the API.
		$pdo = $this->db->getDatabase();
		$result = $pdo->query( "
			select * from pending
			where gateway='test'
				and order_id = '{$message['order_id']}'" );
		$rows = $result->fetchAll( PDO::FETCH_ASSOC );
		$this->assertEquals( 1, count( $rows ),
			'One row stored and retrieved.' );
		$expected = array(
			'id' => '1',
			# NOTE: This is a db-specific string, sqlite3 in this case, and
			# you'll have different formatting if using any other database.
			'date' => '20160720001408',
			'gateway' => 'test',
			'gateway_account' => 'default',
			'order_id' => $message['order_id'],
			'gateway_txn_id' => $message['gateway_txn_id'],
			'message' => json_encode( $message ),
		);
		$this->assertEquals( $expected, $rows[0],
			'Stored message had expected contents' );
	}

	public function testFetchMessageByGatewayOrderId() {
		$message = self::getTestMessage();
		$this->db->storeMessage( $message );

		$fetched = $this->db->fetchMessageByGatewayOrderId( 'test', $message['order_id'] );
		$this->assertNotNull( $fetched,
			'Record retrieved by fetchMessageByGatewayOrderId.' );

		$this->assertEquals( $message, $fetched,
			'Fetched record matches stored message.' );
	}

	public function testFetchMessageByGatewayOldest() {
		$message1 = $this->getTestMessage();
		$message2 = $this->getTestMessage();

		// Make the second message newer.
		$message2['date'] = $message1['date'] - 100;

		$this->db->storeMessage( $message1 );
		$this->db->storeMessage( $message2 );

		$fetched = $this->db->fetchMessageByGatewayOldest( 'test' );
		$this->assertNotNull( $fetched,
			'Retrieved a record using fetchMessageByGatewayOldest' );
		$this->assertEquals( $message2['date'], $fetched['date'],
			'Got the oldest record.' );
	}

	public function testDeleteMessage() {
		$uniq = mt_rand();
		$message1 = $this->getTestMessage( $uniq );
		// Store a second message for a good time, and make sure we delete the
		// right one.
		$message2 = $this->getTestMessage( $uniq );

		$this->db->storeMessage( $message1 );
		$this->db->storeMessage( $message2 );

		// Confirm work without using the API.
		$pdo = $this->db->getDatabase();
		$result = $pdo->query( "
			select * from pending
			where gateway='test'
				and order_id = '{$message1['order_id']}'" );
		$rows = $result->fetchAll( PDO::FETCH_ASSOC );
		$this->assertEquals( 2, count( $rows ),
			'Both records were stored.' );
		$this->assertNotNull( $rows[0]['id'],
			'Record includes a primary row id' );
		$this->assertNotEquals( $rows[0]['id'], $rows[1]['id'],
			'Records have unique primary ids' );

		$this->db->deleteMessage( $message1 );

		// Confirm work without using the API.
		$pdo = $this->db->getDatabase();
		$result = $pdo->query( "
			select * from pending
			where gateway = 'test'
				and order_id = '{$message1['order_id']}'" );
		$rows = $result->fetchAll( PDO::FETCH_ASSOC );
		$this->assertEquals( 0, count( $rows ),
			'All rows deleted.' );
	}
}
