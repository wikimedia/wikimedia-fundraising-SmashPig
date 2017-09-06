<?php
namespace SmashPig\Core\DataStores;

use PDO;
use SmashPig\Core\UtcDate;
use SmashPig\CrmLink\Messages\DateFields;

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
	 */
	public function storeMessage(
		$message,
		$originalQueue,
		$error = '',
		$trace = '',
		$retryDate = null
	) {
		$now = UtcDate::getUtcTimestamp();
		$originalDate = UtcDate::getUtcDatabaseString(
			DateFields::getOriginalDateOrDefault( $message, $now )
		);

		$dbRecord = [
			'original_date' => $originalDate,
			'damaged_date' => UtcDate::getUtcDatabaseString(),
			'original_queue' => $originalQueue,
			'error' => $error,
			'trace' => $trace,
			'message' => json_encode( $message ),
		];
		if ( $retryDate ) {
			$dbRecord['retry_date'] = UtcDate::getUtcDatabaseString(
				$retryDate
			);
		}

		// These fields have their own columns in the database
		// Copy the values from the message to the record
		$indexedFields = [
			'gateway', 'gateway_txn_id', 'order_id'
		];

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

		$this->prepareAndExecute( $insert, $dbRecord );

		return $this->getDatabase()->lastInsertId();
	}

	/**
	 * Return messages ready to be retried
	 *
	 * @param int $limit number of records to return
	 * @return array|null Records with retry_date prior to now
	 */
	public function fetchRetryMessages( $limit ) {
		$sql = 'SELECT * FROM damaged
			WHERE retry_date < :now
			ORDER BY retry_date ASC
			LIMIT ' . $limit;

		$params = [
			'now' => UtcDate::getUtcDatabaseString()
		];

		$executed = $this->prepareAndExecute( $sql, $params );

		$rows = $executed->fetchAll( PDO::FETCH_ASSOC );
		return array_map(
			[ $this, 'messageFromDbRow' ],
			$rows
		);
	}

	/**
	 * Delete a message from the database
	 *
	 * @param array $message
	 */
	public function deleteMessage( $message ) {
		$sql = 'DELETE FROM damaged
			WHERE id = :id';
		$params = [
			'id' => $message['damaged_id']
		];
		$this->prepareAndExecute( $sql, $params );
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
		$params = [
			'date' => UtcDate::getUtcDatabaseString( $originalDate ),
		];
		if ( $queue ) {
			$params['queue'] = $queue;
		}
		$this->prepareAndExecute( $sql, $params );
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
