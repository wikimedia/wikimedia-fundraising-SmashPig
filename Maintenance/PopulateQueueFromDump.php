<?php namespace SmashPig\Maintenance;

require( 'MaintenanceBase.php' );

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\StompDataStore;

$maintClass = '\SmashPig\Maintenance\PopulateQueueFromDump';

/**
 * Script to import a file created by EmptyQueueToDump back into a backing STOMP queue.
 *
 * @package SmashPig\Maintenance
 */
class PopulateQueueFromDump extends MaintenanceBase {

	protected $datastore = null;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to inject into', 'test' );
		$this->addArgument( 'file', 'File, created by EmptyQueueToDump, with JSON format messages to inject', true );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$this->datastore = new StompDataStore( $this->getOption( 'queue' ) );

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
			$parts = explode( '=', $line, 2 );
			if ( count( $parts ) !== 2 ) {
				$this->error( "Improperly formatted line: $line" );
				continue;
			}

			$obj = json_decode( $parts[ 1 ], true );
			if ( !array_key_exists( 'headers', $obj ) || !array_key_exists( 'body', $obj ) ) {
				$this->error( "Decoded line does not have headers and body elements: $line" );
				continue;
			}

			$this->datastore->addObjectRaw( $obj[ 'body' ], $obj[ 'headers' ] );

			$messageCount++;
			if ( $messageCount % 1000 == 0 ) {
				print( '.' );
			}
		}
		print( '\n' );

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Imported $messageCount messages from $infile in $elapsedTime seconds."
		);
	}

}

require( RUN_MAINTENANCE_IF_MAIN );
