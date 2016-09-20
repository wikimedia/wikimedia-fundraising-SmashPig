<?php namespace SmashPig\Core\QueueConsumers;

use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;

class PendingQueueConsumer extends BaseQueueConsumer {

	/**
	 * @var PendingDatabase
	 */
	protected $pendingDatabase;

	/**
	 * @var PaymentsInitialDatabase
	 */
	protected $paymentsInitialDatabase;

	public function __construct( $queueName, $timeLimit, $messageLimit ) {
		parent::__construct( $queueName, $timeLimit, $messageLimit );
		$this->pendingDatabase = PendingDatabase::get();
		$this->paymentsInitialDatabase = PaymentsInitialDatabase::get();
	}

	public function processMessage( $message ) {
		$logIdentifier = "message with gateway {$message['gateway']}" .
			" and order ID {$message['order_id']}";
		if ( $this->paymentsInitialDatabase->isTransactionFinalized( $message ) ) {
			// Throw the message out if it's already completed or failed, and
			// exists in the fredge database.
			Logger::info( "Skipping finalized $logIdentifier" );
		} else {
			Logger::info( "Storing $logIdentifier in database" );
			$this->pendingDatabase->storeMessage( $message );
		}
	}
}
