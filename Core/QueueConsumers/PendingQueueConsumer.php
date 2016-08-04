<?php namespace SmashPig\Core\QueueConsumers;

use SmashPig\Core\DataStores\PendingDatabase;

class PendingQueueConsumer extends BaseQueueConsumer {

	protected $pendingDatabase;

	public function __construct( $queueName, $timeLimit, $messageLimit ) {
		parent::__construct( $queueName, $timeLimit, $messageLimit );
		$this->pendingDatabase = PendingDatabase::get();
	}

	public function processMessage( $message ) {
		$this->pendingDatabase->storeMessage( $message );
	}
}
