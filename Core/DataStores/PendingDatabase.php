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
	 */
	protected $db;

	protected function __construct() {
		$config = Context::get()->getConfiguration();
		$this->db = $config->object( 'data-store/pending-db' );
	}

	/**
	 * @return PDO
	 */
	public function getDatabase() {
		return $this->db;
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
		$prepared = $this->db->prepare( $insert );

		foreach ( $dbRecord as $field => $value ) {
			$prepared->bindValue(
				':' . $field,
				$value,
				PDO::PARAM_STR
			);
		}
		$prepared->execute();
	}
}
