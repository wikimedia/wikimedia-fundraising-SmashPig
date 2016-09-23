<?php
namespace SmashPig\Core\DataStores;

use PDO;
use SmashPig\Core\SmashPigException;
use SmashPig\Core\UtcDate;

/**
 * Data store containing messages which were not successfully processed
 */
class DamagedDatabase extends SmashPigDatabase {

	/**
	 * Build and insert a database record from a queue message
	 *
	 * @param array $message Unprocessable message
	 * @param string $originalQueue Queue the message was first sent to
	 * @param string $error Information about why this message is damaged
	 * @param string $trace Full stack trace
	 * @param int|null $retryDate When provided, re-process message after
	 *  this timestamp
	 * @return int ID of message in damaged database
	 * @throws SmashPigException if insert fails
	 */
	public function storeMessage(
		$message,
		$originalQueue,
		$error = '',
		$trace = '',
		$retryDate = null
	) {
		$originalDate = empty( $message['date'] )
			? UtcDate::getUtcDatabaseString()
			: UtcDate::getUtcDatabaseString( $message['date'] );

		$dbRecord = array(
			'original_date' => $originalDate,
			'damaged_date' => UtcDate::getUtcDatabaseString(),
			'original_queue' => $originalQueue,
			'error' => $error,
			'trace' => $trace,
			'message' => json_encode( $message ),
		);
		if ( $retryDate ) {
			$dbRecord['retry_date'] = UtcDate::getUtcDatabaseString(
				$retryDate
			);
		}

		// These fields have their own columns in the database
		// Copy the values from the message to the record
		$indexedFields = array(
			'gateway', 'gateway_txn_id', 'order_id'
		);

		foreach ( $indexedFields as $fieldName ) {
			if ( isset( $message[$fieldName] ) ) {
				$dbRecord[$fieldName] = $message[$fieldName];
			}
		}

		list( $fieldList, $paramList ) = self::formatInsertParameters(
			$dbRecord
		);

		$insert = "INSERT INTO damaged ( $fieldList )
			VALUES ( $paramList );";

		if ( $this->prepareAndExecute( $insert, $dbRecord ) ) {
			return $this->getDatabase()->lastInsertId();
		}
		throw new SmashPigException( 'Unable to insert into damaged db' );
	}

	/**
	 * Return messages ready to be retried
	 *
	 * @param int $limit number of records to return
	 * @return array|null Records with retry_date prior to now
	 */
	public function fetchRetryMessages( $limit ) {
		$prepared = self::$db->prepare(
			'
			SELECT * FROM damaged
			WHERE retry_date < :now
			ORDER BY retry_date ASC
			LIMIT ' . $limit
		);
		$prepared->bindValue(
			':now',
			UtcDate::getUtcDatabaseString(),
			PDO::PARAM_STR
		);
		$prepared->execute();
		$rows = $prepared->fetchAll( PDO::FETCH_ASSOC );
		return array_map(
			array( $this, 'messageFromDbRow' ),
			$rows
		);
	}

	/**
	 * Delete a message from the database
	 *
	 * @param array $message
	 */
	public function deleteMessage( $message ) {
		$prepared = self::$db->prepare(
			'
			DELETE FROM damaged
			WHERE id = :id'
		);
		$prepared->bindValue( ':id', $message['damaged_id'], PDO::PARAM_STR );
		$prepared->execute();
	}

	/**
	 * Delete expired messages, optionally by original queue
	 *
	 * @param int $originalDate Oldest original timestamp to keep
	 * @param string|null $queue
	 */
	public function deleteOldMessages( $originalDate, $queue = null ) {
		$sql = 'DELETE FROM damaged WHERE original_date < :date';
		if ( $queue ) {
			$sql .= ' AND original_queue = :queue';
		}
		$prepared = self::$db->prepare( $sql );
		$prepared->bindValue(
			':date',
			UtcDate::getUtcDatabaseString( $originalDate ),
			PDO::PARAM_STR
		);
		if ( $queue ) {
			$prepared->bindValue( ':queue', $queue, PDO::PARAM_STR );
		}
		$prepared->execute();
	}

	/**
	 * Parse a database row and return the normalized message.
	 * @param array $row
	 * @return array
	 */
	protected function messageFromDbRow( $row ) {
		$message = json_decode( $row['message'], true );
		$message['damaged_id'] = $row['id'];
		$message['original_queue'] = $row['original_queue'];
		return $message;
	}

	protected function getConfigKey() {
		return 'data-store/damaged-db';
	}

	protected function getTableScriptFile() {
		return '002_CreateDamagedTable.sql';
	}
}
