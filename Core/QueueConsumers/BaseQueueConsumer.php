<?php
namespace SmashPig\Core\QueueConsumers;

use Exception;
use InvalidArgumentException;
use PHPQueue\Interfaces\AtomicReadBuffer;

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\DamagedDatabase;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\RetryableException;
use SmashPig\Core\UtcDate;

/**
 * Facilitates guaranteed message processing using PHPQueue's AtomicReadBuffer
 * interface. Exceptions in the processing callback will cause the message to
 * be sent to a damaged message datastore.
 */
abstract class BaseQueueConsumer {

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
	 * Do something with the message popped from the queue. Return value is
	 * ignored, and exceptions will be caught and handled by handleError.
	 *
	 * @param array $message
	 */
	abstract function processMessage( $message );

	/**
	 * Gets a fresh QueueConsumer
	 *
	 * @param string $queueName key of queue configured in data-store, must
	 *  implement @see PHPQueue\Interfaces\AtomicReadBuffer.
	 * @param int $timeLimit max number of seconds to loop, 0 for no limit
	 * @param int $messageLimit max number of messages to process, 0 for all
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function __construct(
		$queueName,
		$timeLimit = 0,
		$messageLimit = 0
	) {
		if ( !is_numeric( $timeLimit ) ) {
			throw new InvalidArgumentException( 'timeLimit must be numeric' );
		}
		if ( !is_numeric( $messageLimit ) ) {
			throw new InvalidArgumentException( 'messageLimit must be numeric' );
		}

		$this->queueName = $queueName;
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
		$realCallback = array( $this, 'processMessageWithErrorHandling' );
		do {
			$data = $this->backend->popAtomic( $realCallback );
			if ( $data !== null ) {
				$processed++;
			}
			$timeOk = $this->timeLimit === 0 || time() <= $startTime + $this->timeLimit;
			$countOk = $this->messageLimit === 0 || $processed < $this->messageLimit;
			$debugMessage = 'Data is ' . ( $data === null ? '' : 'not ' ) . 'null, ' .
				"time limit ($this->timeLimit) is " . ( $timeOk ? 'not ' : '' ) . 'elapsed, ' .
				"message limit ($this->messageLimit) is " . ( $countOk ? 'not ' : '' ) . 'reached.';
			Logger::debug( $debugMessage );
		}
		while( $timeOk && $countOk && $data !== null );
		return $processed;
	}

	/**
	 * Call the concrete processMessage function and handle any errors that
	 * may arise.
	 *
	 * @param array $message
	 */
	public function processMessageWithErrorHandling( $message ) {
		try {
			$this->processMessage( $message );
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
		if ( $ex instanceof RetryableException ) {
			$now = UtcDate::getUtcTimestamp();

			if ( !isset( $message['source_enqueued_time'] ) ) {
				$message['source_enqueued_time'] = UtcDate::getUtcTimestamp();
			}
			$expirationDate = $message['source_enqueued_time'] +
				Configuration::getDefaultConfig()->val( 'requeue-max-age' );

			if ( $now < $expirationDate ) {
				$retryDate = $now + Configuration::getDefaultConfig()->val( 'requeue-delay' );
				$this->sendToDamagedStore( $message, $ex, $retryDate );
				return;
			}
		}
		$this->sendToDamagedStore( $message, $ex );
	}

	/**
	 * @param array $message The data
	 * @param Exception $ex The problem
	 * @param int| null $retryDate If provided, retry after this timestamp
	 * @return int ID of message in damaged database
	 */
	protected function sendToDamagedStore(
		$message, Exception $ex, $retryDate = null
	) {
		if ( $retryDate ) {
			Logger::notice(
				'Message not fully baked. Sticking it back in the oven, to ' .
				"retry at $retryDate",
				$message
			);
		} else {
			Logger::error(
				'Error processing message, moving to damaged store.',
				$message,
				$ex
			);
		}
		return $this->damagedDb->storeMessage(
			$message,
			$this->queueName,
			$ex->getMessage(),
			$ex->getTraceAsString(),
			$retryDate
		);
	}

	public static function getQueue( $queueName ) {
		$config = Context::get()->getConfiguration();
		$key = "data-store/$queueName";
		Logger::debug( "Getting queue $queueName from key $key" );

		// Get a reference to the config node so we can mess with it
		$node =& $config->val( $key, true );
		if (
			empty( $node['constructor-parameters'] ) ||
			empty( $node['constructor-parameters'][0]['queue'] )
		) {
			Logger::debug( "'queue' not set, defaulting to $queueName" );
			$node['constructor-parameters'][0]['queue'] = $queueName;
		}

		return $config->object( $key, true );
	}
}
