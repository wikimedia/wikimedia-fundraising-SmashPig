<?php
namespace SmashPig\Core\DataStores;

use Exception;
use InvalidArgumentException;
use PHPQueue\Interfaces\AtomicReadBuffer;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

/**
 * Facilitates guaranteed message processing using PHPQueue's AtomicReadBuffer
 * interface. Exceptions in the processing callback will cause the message to
 * be sent to a damaged message datastore.
 */
class QueueConsumer {

	/**
	 * @var AtomicReadBuffer
	 */
	protected $backend;

	protected $queueName;

	/**
	 * @var callable
	 */
	protected $callback;

	/**
	 * @var DamagedDatabase
	 */
	protected $damagedDb;

	protected $timeLimit = 0;

	protected $messageLimit = 0;

	/**
	 * Gets a fresh QueueConsumer
	 *
	 * @param string $queueName key of queue configured in data-store, must
	 *  implement @see PHPQueue\Interfaces\AtomicReadBuffer.
	 * @param callable $callback processing function taking message array
	 * @param int $timeLimit max number of seconds to loop, 0 for no limit
	 * @param int $messageLimit max number of messages to process, 0 for all
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function __construct(
		$queueName,
		$callback,
		$timeLimit = 0,
		$messageLimit = 0
	) {
		if ( !is_numeric( $timeLimit ) ) {
			throw new InvalidArgumentException( 'timeLimit must be numeric' );
		}
		if ( !is_numeric( $messageLimit ) ) {
			throw new InvalidArgumentException( 'messageLimit must be numeric' );
		}
		if ( !is_callable( $callback ) ) {
			throw new InvalidArgumentException( "Processing callback must be callable" );
		}

		$this->queueName = $queueName;
		$this->callback = $callback;
		$this->timeLimit = intval( $timeLimit );
		$this->messageLimit = intval( $messageLimit );

		$this->backend = self::getQueue( $queueName );

		if ( !$this->backend instanceof AtomicReadBuffer ) {
			throw new InvalidArgumentException(
				"Queue $queueName is not an AtomicReadBuffer"
			);
		}

		$this->damagedDb = DamagedDatabase::get();
	}

	/**
	 * Dequeue and process messages until time limit or message limit is
	 * reached, or till queue is empty.
	 *
	 * @return int number of messages processed
	 */
	public function dequeueMessages() {
		$startTime = time();
		$processed = 0;
		$realCallback = array( $this, 'processMessage' );
		do {
			$data = $this->backend->popAtomic( $realCallback );
			if ( $data !== null ) {
				$processed++;
			}
			$timeOk = $this->timeLimit === 0 || time() <= $startTime + $this->timeLimit;
			$countOk = $this->messageLimit === 0 || $processed < $this->messageLimit;
		}
		while( $timeOk && $countOk && $data !== null );
		return $processed;
	}

	public function processMessage( $message ) {
		try {
			call_user_func( $this->callback, $message );
		} catch ( Exception $ex ) {
			$this->handleError( $message, $ex );
		}
	}

	/**
	 * Using an AtomicReadBuffer implementation for the backend means that
	 * if this throws an exception, the message will remain on the queue.
	 *
	 * @param array $message
	 * @param Exception $ex
	 */
	protected function handleError( $message, Exception $ex ) {
		Logger::error(
			'Error processing message, moving to damaged queue.',
			$message,
			$ex
		);
		$this->damagedDb->storeMessage(
			$message,
			$this->queueName,
			$ex->getMessage() . "\n" . $ex->getTraceAsString()
		);
	}

	public static function getQueue( $queueName ) {
		$config = Context::get()->getConfiguration();
		$key = "data-store/$queueName";

		// Get a reference to the config node so we can mess with it
		$node =& $config->val( $key, true );
		if (
			empty( $node['inst-args'] ) ||
			empty( $node['inst-args'][0]['queue'] )
		) {
			$node['inst-args'][0]['queue'] = $queueName;
		}

		return $config->object( $key, true );
	}
}
