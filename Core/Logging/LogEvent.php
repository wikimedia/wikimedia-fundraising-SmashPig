<?php namespace SmashPig\Core\Logging;

use SmashPig\Core\DataStores\DataSerializationException;
use SmashPig\Core\DataStores\JsonSerializableObject;

class LogEvent {
	/** @var int Event priority, e.g. LOG_DEBUG */
	public $level;

	/** @var string Identifying subclass of this event */
	public $tag;

	/** @var string ISO time string of event */
	public $datestring;

	/** @var int System time in seconds of event */
	public $timestamp;

	/** @var string Human readable message for the event */
	public $message;

	/** @var string Serialized data attached to the event */
	public $data;

	/** @var \Exception exception object thrown coincident to the event */
	public $exception;

	/**
	 * Construct a new log event from parameters.
	 *
	 * @param int        $level     The RFC log level, e.g. LOG_ALERT
	 * @param string     $message   Human readable string about the event. Do not include sensitive information here
	 * @param string     $tag       Optional descriptive tag, e.g. RawData
	 * @param mixed      $data      Optional data object (should be serializable); may include sensitive information
	 * @param \Exception $exception Optional exception object related to this event
	 * @param int|string $timestamp Optional Unix timestamp, or date string of event. If not given this assumes now
	 */
	public function __construct(
		$level, $message, $tag = null, $data = null, \Exception $exception = null, $timestamp = null
	) {
		if ( !is_int( $level ) || ( $level > LOG_DEBUG ) || ( $level < LOG_ALERT ) ) {
			$this->level = LOG_ERR;
		} else {
			$this->level = $level;
		}

		$this->message = $message;
		$this->tag = $tag;
		$this->exception = $exception;

		if ( !is_null( $data ) ) {
			$jdata = false;
			if ( $data instanceof JsonSerializableObject ) {
				try {
					$jdata = $data->toJson();
				}
				catch( DataSerializationException $ex ) {}
			} else {
				$jdata = json_encode( $data );
			}

			if ( $jdata ) {
				$this->data = $jdata;
			} else {
				$this->data = '"!!NON SERIALIZABLE DATA!!"';
			}
		} else {
			$this->data = null;
		}

		if ( !$timestamp ) {
			$this->timestamp = time();
			$this->datestring = date( 'c' );
		} elseif ( is_int( $timestamp ) ) {
			$this->timestamp = $timestamp;
			$this->datestring = date( 'c', $timestamp );
		} elseif ( is_string( $timestamp ) ) {
			$this->datestring = $timestamp;
			$this->timestamp = strtotime( $timestamp );
		}
	}

	/**
	 * Format the exception to be human readable
	 *
	 * @return array The first element is the header, e.g. the type, location, line, and message. Following
	 * elements contain each individual stack trace line.
	 */
	public function getExceptionBlob() {
		$cex = $this->exception;
		if ( !$cex ) {
			return array();
		}

		// Get the caused by header
		$descStr = array();
		do {
			$descStr[] = get_class( $cex ) . "@{$cex->getFile()}:{$cex->getLine()} ({$cex->getMessage()})";
			$cex = $cex->getPrevious();
		} while ( $cex );
		$descStr = implode( ' -> ', array_reverse( $descStr ) );

		// Get the stack trace
		$stack = explode( "\n", $this->exception->getTraceAsString() );

		array_unshift( $stack, $descStr );
		return $stack;
	}
}
