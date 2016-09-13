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
		if ( $this->paymentsInitialDatabase->isTransactionFinalized( $message ) ) {
			// Throw the message out if it's already completed or failed, and
			// exists in the fredge database.
			Logger::info( "Skipping finalized message", $message );
		} else {
			Logger::info( "Storing message in database", $message );
			$this->pendingDatabase->storeMessage( $message );
		}
	}
}
