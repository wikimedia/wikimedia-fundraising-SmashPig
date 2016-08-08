<?php namespace SmashPig\Tests;

use SmashPig\Core\QueueConsumers\BaseQueueConsumer;

class TestingQueueConsumer extends BaseQueueConsumer {

	public $exception;
	public $processed = array();

	function processMessage( $message ) {
		$this->processed[] = $message;
		if ( $this->exception ) {
			throw $this->exception;
		}
	}
}
