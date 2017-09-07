<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\QueueWrapper;

$maintClass = '\SmashPig\Maintenance\PopulateQueueFromDump';

/**
 * Script to import a file created by EmptyQueueToDump back into a PHPQueue backend.
 * Handles both 'raw' dumps from old Stomp queues and normal dumps with one JSON-
 * encoded message body per line.
 *
 * @package SmashPig\Maintenance
 */
class PopulateQueueFromDump extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to inject into', 'test' );
		$this->addArgument( 'file',
			'File, created by EmptyQueueToDump, with JSON format messages to inject', true );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$datastore = QueueWrapper::getQueue(
			$this->getOption( 'queue' )
		);

		$startTime = time();
		$messageCount = 0;

		// Open the file for read
		$infile = $this->getArgument( 'file' );
		$f = fopen( $infile, 'r' );
		if ( !$f ) {
			$this->error( "Could not open $infile for read", true );
		}

		// Do the loop!
		while ( ( $line = fgets( $f ) ) !== false ) {
			if ( substr( $line, 0, 4 ) === 'raw=' ) {
				$message = $this->decodeLegacyMessage( $line );
			} else {
				$message = json_decode( $line, true );
			}
			if ( $message === null ) {
				Logger::error( "Invalid line: $line" );
				continue;
			}

			// push message directly to queue, bypassing QueueWrapper's adding
			// source fields.
			$datastore->push( $message );

			$messageCount++;
			if ( $messageCount % 1000 == 0 ) {
				print ( '.' );
			}
		}
		print ( '\n' );

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Imported $messageCount messages from $infile in $elapsedTime seconds."
		);
	}

	protected function decodeLegacyMessage( $line ) {
		$parts = explode( '=', $line, 2 );

		$obj = json_decode( $parts[ 1 ], true );
		if ( !array_key_exists( 'headers', $obj ) || !array_key_exists( 'body', $obj ) ) {
			return null;
		}
		// JSON-fried JSON
		return json_decode( $obj['body'], true );
	}

}

require RUN_MAINTENANCE_IF_MAIN;
