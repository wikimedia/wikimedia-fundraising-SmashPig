<?php
namespace SmashPig\Core\DataStores;

use PDO;
use SmashPig\Core\Context;
use SmashPig\Core\SmashPigException;
use SmashPig\Core\UtcDate;

/**
 * Data store containing messages waiting to be finalized.
 */
class PendingDatabase {

	/**
	 * @var PDO
	 * We do the silly singleton thing for convenient testing with in-memory
	 * databases that would otherwise not be shared between components.
	 */
	protected static $db;

	protected function __construct() {
		$config = Context::get()->getConfiguration();
		if ( !self::$db ) {
			self::$db = $config->object( 'data-store/pending-db' );
		}
	}

	/**
	 * @return PDO
	 */
	public function getDatabase() {
		return self::$db;
	}

	public static function get() {
		// This is static so we have the option to go singleton if needed.
		return new PendingDatabase();
	}

	protected function validateMessage( $message ) {
		if (
			empty( $message['date'] ) ||
			empty( $message['gateway'] ) ||
			(   // need at least one transaction ID
				empty( $message['gateway_txn_id'] ) &&
				empty( $message['order_id'] )
			)
		) {
			throw new SmashPigException( 'Message missing required fields' );
		}
	}

	/**
	 * Build and insert a database record from a pending queue message
	 *
	 * @param array $message
	 * @throws SmashPigException
	 */
	public function storeMessage( $message ) {
		$this->validateMessage( $message );

		$dbRecord = array();

		// These fields (and date) have their own columns in the database
		// Copy the values from the message to the record
		$indexedFields = array(
			'gateway', 'gateway_account', 'gateway_txn_id', 'order_id'
		);

		foreach ( $indexedFields as $fieldName ) {
			if ( isset( $message[$fieldName] ) ) {
				$dbRecord[$fieldName] = $message[$fieldName];
			}
		}

		$dbRecord['date'] = UtcDate::getUtcDatabaseString( $message['date'] );
		// Dump the whole message into a text column
		$dbRecord['message'] = json_encode( $message );

		$fieldList = implode( ',', array_keys( $dbRecord ) );

		// Build a list of parameter names for safe db insert
		// Same as the field list, but each parameter is prefixed with a colon
		$paramList = ':' . implode( ', :', array_keys( $dbRecord ) );

		$insert = "INSERT INTO pending ( $fieldList ) values ( $paramList );";
		$prepared = self::$db->prepare( $insert );

		foreach ( $dbRecord as $field => $value ) {
			$prepared->bindValue(
				':' . $field,
				$value,
				PDO::PARAM_STR
			);
		}
		$prepared->execute();
	}

	/**
	 * Return record matching a (gateway, order_id), or null
	 *
	 * @param $gatewayName string
	 * @param $orderId string
	 * @return array|null Record related to a transaction, or null if nothing matches
	 */
	public function fetchMessageByGatewayOrderId( $gatewayName, $orderId ) {
		$prepared = self::$db->prepare( '
			select * from pending
			where gateway = :gateway
				and order_id = :order_id
			limit 1' );
		$prepared->bindValue( ':gateway', $gatewayName, PDO::PARAM_STR );
		$prepared->bindValue( ':order_id', $orderId, PDO::PARAM_STR );
		$prepared->execute();
		$row = $prepared->fetch( PDO::FETCH_ASSOC );
		if ( !$row ) {
			return null;
		}
		return $this->messageFromDbRow( $row );
	}

	/**
	 * Get the oldest message for a given gateway, by date
	 *
	 * @param $gatewayName string
	 * @return array|null Message or null if nothing is found.
	 */
	public function fetchMessageByGatewayOldest( $gatewayName ) {
		$prepared = self::$db->prepare( '
			select * from pending
			where gateway = :gateway
			order by date asc
			limit 1' );
		$prepared->bindValue( ':gateway', $gatewayName, PDO::PARAM_STR );
		$prepared->execute();
		$row = $prepared->fetch( PDO::FETCH_ASSOC );
		if ( !$row ) {
			return null;
		}
		return $this->messageFromDbRow( $row );
	}

	/**
	 * Get the newest N messages for a given gateway.
	 *
	 * @param string $gatewayName
	 * @param int $limit fetch at most this many messages
	 * @return array|null Messages or null if nothing is found.
	 */
	public function fetchMessagesByGatewayNewest( $gatewayName, $limit = 1 ) {
		$prepared = self::$db->prepare( "
			select * from pending
			where gateway = :gateway
			order by date desc
			limit $limit" );
		$prepared->bindValue( ':gateway', $gatewayName, PDO::PARAM_STR );
		$prepared->execute();
		$rows = $prepared->fetchAll( PDO::FETCH_ASSOC );
		if ( !$rows ) {
			return null;
		}
		$messages = array_map( function( $row ) {
			return json_decode( $row['message'], true );
		}, $rows);

		return $messages;
	}

	/**
	 * Delete a message from the database
	 *
	 * Note that we delete by (gateway, order_id) internally.
	 *
	 * @param array $message
	 */
	public function deleteMessage( $message ) {
		$prepared = self::$db->prepare( '
			delete from pending
			where gateway = :gateway
				and order_id = :order_id' );
		$prepared->bindValue( ':gateway', $message['gateway'], PDO::PARAM_STR );
		$prepared->bindValue( ':order_id', $message['order_id'], PDO::PARAM_STR );
		$prepared->execute();
	}

	/**
	 * Parse a database row and return the normalized message.
	 */
	protected function messageFromDbRow( $row ) {
		$message = json_decode( $row['message'], true );
		$message['pending_id'] = $row['id'];
		return $message;
	}
}
