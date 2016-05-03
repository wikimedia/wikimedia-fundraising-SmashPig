<?php
namespace SmashPig\Core\DataStores;

use InvalidArgumentException;
use PHPQueue\Interfaces\AtomicReadBuffer;

use SmashPig\Core\Context;

/**
 * Facilitates guaranteed message processing using PHPQueue's
 * AtomicReadBuffer interface.
 */
class QueueConsumer {

	/**
	 * @var AtomicReadBuffer
	 */
	protected $backend;

	/**
	 * @var callable
	 */
	protected $callback;

	protected $timeLimit = 0;

	protected $messageLimit = 0;

	/**
	 * Gets a fresh QueueConsumer
	 *
	 * @param string $queueName key of queue configured in data-stores/queues,
	 *                          must implement @see AtomicReadBuffer
	 * @param callable $callback processing function taking message array
	 * @param int $timeLimit max number of seconds to loop, 0 for no limit
	 * @param int $messageLimit max number of messages to process, 0 for all
	 */
	public function __construct( $queueName, $callback, $timeLimit = 0, $messageLimit = 0 ) {
		$this->callback = $callback;
		$this->timeLimit = $timeLimit;
		$this->messageLimit = $messageLimit;

		$config = Context::get()->getConfiguration();
		$this->backend = $config->object(
			"data-store/$queueName", true
		);

		if ( !$this->backend instanceof AtomicReadBuffer ) {
			throw new InvalidArgumentException( "Queue $queueName is not an AtomicReadBuffer" );
		}

		if ( !is_callable( $callback ) ) {
			throw new InvalidArgumentException( "Processing callback is not callable" );
		}
	}

	/**
	 * Dequeue and process messages until time limit or message limit is
	 * reached, or till queue is empty. Using an @see AtomicReadBuffer
	 * implementation for the backend means that if the processing function
	 * throws an exception, the message will remain on the queue.
	 *
	 * @return int number of messages processed
	 */
	public function dequeueMessages() {
		$startTime = time();
		$processed = 0;
		do {
			$data = $this->backend->popAtomic( $this->callback );
			if ( $data !== null ) {
				$processed++;
			}
			$timeOk = $this->timeLimit === 0 || time() <= $startTime + $this->timeLimit;
			$countOk = $this->messageLimit === 0 || $processed <= $this->messageLimit;
		}
		while( $timeOk && $countOk && $data !== null );
		return $processed;
	}

}
