<?php namespace SmashPig\Core\QueueConsumers;

use RuntimeException;

class QueueFileDumper extends BaseQueueConsumer {

	/**
	 * @resource
	 */
	protected $file;

	/**
	 * QueueFileDumper constructor.
	 * @param string $queueName
	 * @param int $messageLimit
	 * @param string $filename
	 */
	public function __construct( $queueName, $messageLimit, $filename ) {
		parent::__construct( $queueName, 0, $messageLimit );
		$this->file = fopen( $filename, 'a' );
		if ( !$this->file ) {
			throw new RuntimeException( "Can't open $filename for appending" );
		}
	}

	public function processMessage( $message ) {
		fwrite( $this->file, json_encode( $message, true ) . "\n" );
	}

	public function __destruct() {
		fclose( $this->file );
	}
}
