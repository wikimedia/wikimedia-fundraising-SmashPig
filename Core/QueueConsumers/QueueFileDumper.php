<?php namespace SmashPig\Core\QueueConsumers;

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
	}

	public function processMessage( $message ) {
		fwrite( $this->file, json_encode( $message, true ) . "\n" );
	}

	public function __destruct() {
		fclose( $this->file );
	}
}
