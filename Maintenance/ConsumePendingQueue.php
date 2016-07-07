<?php
namespace SmashPig\Maintenance;

require ( 'MaintenanceBase.php' );

use \PDO;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\QueueConsumer;
use SmashPig\Core\SmashPigException;
use SmashPig\Core\UtcDate;

$maintClass = '\SmashPig\Maintenance\ConsumePendingQueue';

/**
 * Reads messages out of the pending queue and inserts them into a db table
 */
class ConsumePendingQueue extends MaintenanceBase {

	/**
	 * @var PDO
	 */
	protected $pendingDatabase = null;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to consume from', 'pending-atomic' );
		$this->addOption( 'time-limit', 'Try to keep execution under <n> seconds', 60, 't' );
		$this->addOption( 'max-messages', 'At most consume <n> messages', 0, 'm' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$config = Context::get()->getConfiguration();
		$this->pendingDatabase = $config->object( 'data-store/pending-db' );

		$basePath = 'maintenance/consume-pending/';
		$consumer = new QueueConsumer(
			$this->getOption( 'queue' ),
			array( $this, 'storeMessage' ),
			$this->getOptionOrConfig( 'time-limit', $basePath . 'time-limit' ),
			$this->getOptionOrConfig( 'max-messages', $basePath . 'message-limit' ),
			'pending-damaged'
		);

		$startTime = time();
		$messageCount = $consumer->dequeueMessages();

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Processed $messageCount pending messages in $elapsedTime seconds."
		);
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
		$prepared = $this->pendingDatabase->prepare( $insert );

		foreach ( $dbRecord as $field => $value ) {
			$prepared->bindValue(
				':' . $field,
				$value,
				PDO::PARAM_STR
			);
		}
		$prepared->execute();
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
}

require ( RUN_MAINTENANCE_IF_MAIN );
