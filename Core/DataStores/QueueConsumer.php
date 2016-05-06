<?php
namespace SmashPig\Core\DataStores;

use Exception;
use InvalidArgumentException;
use PHPQueue\Interfaces\AtomicReadBuffer;
use PHPQueue\Interfaces\FifoQueueStore;
use PHPQueue\Interfaces\IndexedFifoQueueStore;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

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

	protected $damagedQueue;

	protected $timeLimit = 0;

	protected $messageLimit = 0;

	/**
	 * Gets a fresh QueueConsumer
	 *
	 * @param string $queueName key of queue configured in data-store, must
	 *                          implement @see PHPQueue\Interfaces\AtomicReadBuffer
	 * @param callable $callback processing function taking message array
	 * @param int $timeLimit max number of seconds to loop, 0 for no limit
	 * @param int $messageLimit max number of messages to process, 0 for all
	 * @param string $damagedQueue if provided, exceptions in the processing
	 *                             callback will cause the message to be sent
	 *                             to this queue instead of halting the dequeue
	 *                             loop. Must support push()
	 *
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function __construct(
		$queueName,
		$callback,
		$timeLimit = 0,
		$messageLimit = 0,
		$damagedQueue = null
	) {
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

		if ( $damagedQueue ) {
			$this->damagedQueue = $config->object(
				"data-store/$damagedQueue", true
			);

			if (
				!$this->damagedQueue instanceof FifoQueueStore &&
				// FIXME: IndexedFifoQueueStore is deprecated
				!$this->damagedQueue instanceof IndexedFifoQueueStore
			) {
				throw new InvalidArgumentException(
					"Queue $damagedQueue does not support push"
				);
			}
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
		if ( $this->damagedQueue ) {
			$realCallback = array( $this, 'wrappedCallback' );
		} else {
			$realCallback = $this->callback;
		}
		do {
			$data = $this->backend->popAtomic( $realCallback );
			if ( $data !== null ) {
				$processed++;
			}
			$timeOk = $this->timeLimit === 0 || time() <= $startTime + $this->timeLimit;
			$countOk = $this->messageLimit === 0 || $processed <= $this->messageLimit;
		}
		while( $timeOk && $countOk && $data !== null );
		return $processed;
	}

	public function wrappedCallback( $message ) {
		try {
			call_user_func( $this->callback, $message );
		} catch ( Exception $ex ) {
			Logger::error(
				'Error processing message, moving to damaged queue.',
				$message,
				$ex
			);
			$this->damagedQueue->push( $message );
		}
	}
}
