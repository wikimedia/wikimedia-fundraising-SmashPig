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

	public function __construct( string $queueName, int $timeLimit, int $messageLimit ) {
		parent::__construct( $queueName, $timeLimit, $messageLimit );
		$this->pendingDatabase = PendingDatabase::get();
		$this->paymentsInitialDatabase = PaymentsInitialDatabase::get();
	}

	public function processMessage( array $message ) {
		$logIdentifier = "message with gateway {$message['gateway']}" .
			" and order ID {$message['order_id']}";

		if ( $this->paymentsInitialDatabase->isTransactionFailed( $message ) ) {
			// Throw the message out if it's already failed
			Logger::info( "Skipping failed $logIdentifier" );
		} else {
			Logger::info( "Storing $logIdentifier in database" );
			$this->pendingDatabase->storeMessage( $message );
		}
	}
}
