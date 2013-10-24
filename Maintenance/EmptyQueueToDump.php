<?php namespace SmashPig\Maintenance;

require( 'MaintenanceBase.php' );

use SmashPig\Core\DataStores\DataSerializationException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\StompDataStore;

$maintClass = '\SmashPig\Maintenance\EmptyQueueToDump';

/**
 * Script to empty out a queue backing store into a file. Objects are not kept
 * in the queue after dumping.
 *
 * Selectors must be specified when dumping. A selector is a SQL like statement
 * such as 'gateway=adyen'. In general selectors only work on the contents of
 * headers and should only be text. Numeric selectors MAY work but it's
 * protocol and version specific.
 *
 * @package SmashPig\Maintenance
 */
class EmptyQueueToDump extends MaintenanceBase {

	protected $datastore = null;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to consume from', 'test' );
		$this->addOption( 'max-messages', 'At most consume <n> messages, 0 is infinite', 10, 'm' );
		$this->addOption( 'outfile', 'File to place JSON encoded messages into', 'messages.json', 'f' );
		$this->addArgument( 'selector', 'STOMP selector to use', 'true' );
		$this->addArgument( 'selector2', 'Additional STOMP selectors...', false );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$this->datastore = new StompDataStore( $this->getOption( 'queue' ) );

		$maxMessages = $this->getOption( 'max-messages' );
		$startTime = time();
		$messageCount = 0;

		// Construct the selectors
		$argId = 0;
		$selectors = array();
		do {
			$selectors[] = $this->getArgument( $argId );
			$argId += 1;
		} while ( $this->hasArgument( $argId ) );

		// Open the file for write-append
		$outfile = $this->getOption( 'outfile' );
		$f = fopen( $outfile, 'a' );
		if ( !$f ) {
			$this->error( "Could not open $outfile for write append", true );
		}

		// Do the loop!
		do {
			// This is actually quite inefficient; but this whole thing is a hack so meh!
			// ...Ideally we would take the JSON from the store directly instead of walking
			// it through an object
			try {
				$queueObj = $this->datastore->queueGetObject( null, null, $selectors );

				if ( !$queueObj ) {
					break;
				}

				fwrite( $f, get_class( $queueObj ) . "=" . $queueObj->toJson( false ) . "\n" );
			} catch( DataSerializationException $ex ) {
				// We probably caught an anti-message here; log the exception and continue on
				Logger::warning( "Possibly caught an antimessage. Not adding to file.", null, $ex );
			}

			$this->datastore->queueAckObject();

			$messageCount += 1;

		} while ( ( $maxMessages === 0 ) || ( $messageCount < $maxMessages ) );

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Dumped $messageCount messages to $outfile in $elapsedTime seconds."
		);
	}

}

require( RUN_MAINTENANCE_IF_MAIN );