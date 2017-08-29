<?php
namespace SmashPig\Core\DataStores;

use PDO;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;
use SmashPig\Core\UtcDate;

/**
 * Data store containing messages waiting to be finalized.
 */
class PendingDatabase extends SmashPigDatabase {

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
	 * @return int ID of message in pending database
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

		if ( isset( $message['pending_id'] ) ) {
			$sql = $this->getUpdateStatement( $dbRecord );
			$dbRecord['id'] = $message['pending_id'];
		} else {
			$sql = $this->getInsertStatement( $dbRecord );
		}
		$this->prepareAndExecute( $sql, $dbRecord );

		return $this->getDatabase()->lastInsertId();
	}

	/**
	 * Return record matching a (gateway, order_id), or null
	 *
	 * @param $gatewayName string
	 * @param $orderId string
	 * @return array|null Record related to a transaction, or null if nothing matches
	 */
	public function fetchMessageByGatewayOrderId( $gatewayName, $orderId ) {
		$sql = 'select * from pending
			where gateway = :gateway
				and order_id = :order_id
			limit 1';

		$params = array(
			'gateway' => $gatewayName,
			'order_id' => $orderId,
		);
		$executed = $this->prepareAndExecute( $sql, $params );
		$row = $executed->fetch( PDO::FETCH_ASSOC );
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
		$sql = 'select * from pending
			where gateway = :gateway
			order by date asc
			limit 1';

		$params = array( 'gateway' => $gatewayName );
		$executed = $this->prepareAndExecute( $sql, $params );
		$row = $executed->fetch( PDO::FETCH_ASSOC );
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
		$sql = "
			select * from pending
			where gateway = :gateway
			order by date desc
			limit $limit";
		$params = array( 'gateway' => $gatewayName );
		$executed = $this->prepareAndExecute( $sql, $params );
		$rows = $executed->fetchAll( PDO::FETCH_ASSOC );
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
		if ( !isset( $message['order_id'] ) ) {
			$json = json_encode( $message );
			Logger::warning( "Trying to delete pending message with no order id: $json" );
			return;
		}

		$sql = '
			delete from pending
			where gateway = :gateway
				and order_id = :order_id';
		$params = array(
			'gateway' => $message['gateway'],
			'order_id' => $message['order_id'],
		);

		$this->prepareAndExecute( $sql, $params );
	}

	/**
	 * Delete expired messages, optionally by gateway
	 *
	 * @param int $originalDate Oldest date to keep
	 * @param string|null $gateway
	 * @return int Number of rows deleted
	 */
	public function deleteOldMessages( $originalDate, $gateway = null ) {
		$sql = 'DELETE FROM pending WHERE date < :date';
		$params = array(
			'date' => UtcDate::getUtcDatabaseString( $originalDate ),
		);
		if ( $gateway ) {
			$sql .= ' AND gateway = :gateway';
			$params['gateway'] = $gateway;
		}
		$executed = $this->prepareAndExecute( $sql, $params );
		return $executed->rowCount();
	}

	/**
	 * Parse a database row and return the normalized message.
	 */
	protected function messageFromDbRow( $row ) {
		$message = json_decode( $row['message'], true );
		$message['pending_id'] = $row['id'];
		return $message;
	}

	/**
	 * @param array $record
	 * @return string SQL to insert a pending record, with parameters
	 */
	protected function getInsertStatement( $record ) {
		list( $fieldList, $paramList ) = self::formatInsertParameters(
			$record
		);

		$insert = "INSERT INTO pending ( $fieldList ) VALUES ( $paramList )";
		return $insert;
	}

	/**
	 * @param array $record
	 * @return string SQL to update a pending record, with parameters
	 */
	protected function getUpdateStatement( $record ) {
		$sets = array();
		foreach( array_keys( $record ) as $field ) {
			$sets[] = "$field = :$field";
		}
		$update = 'UPDATE pending SET ' .
			implode( ',', $sets ) .
			' WHERE id = :id';
		return $update;
	}

	protected function getConfigKey() {
		return 'data-store/pending-db';
	}

	protected function getTableScriptFile() {
		return '001_CreatePendingTable.sql';
	}
}
