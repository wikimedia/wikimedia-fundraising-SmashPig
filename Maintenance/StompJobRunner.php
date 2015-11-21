<?php namespace SmashPig\Maintenance;

require( 'MaintenanceBase.php' );

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\StompDataStore;
use SmashPig\Core\SmashPigException;

$maintClass = '\SmashPig\Maintenance\StompJobRunner';

/**
 * The job runner script reads job requests from a specified queue and dispatches the requests.
 * It attempts to time limit itself; however a long running job will not be terminated. Rather
 * after it completes no new jobs will be dispatched and this script will exit.
 */
class StompJobRunner extends MaintenanceBase {

	protected $datastore = null;
	protected $damagedDatastore = null;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to consume from', 'jobs' );
		$this->addOption( 'damaged-queue', 'name of queue to hold failed job messages', 'jobs-damaged' );
		$this->addOption( 'time-limit', 'Try to keep execution under <n> seconds', 60, 't' );
		$this->addOption( 'max-messages', 'At most consume <n> messages', 10, 'm' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$this->datastore = new StompDataStore( $this->getOption( 'queue' ) );
		$this->damagedDatastore = new StompDataStore( $this->getOption( 'damaged-queue' ) );

		$startTime = time();
		$messageCount = 0;
		$successCount = 0;

		do {
			$jobObj = $this->datastore->queueGetObject();

			if ( !$jobObj ) {
				// No more jobs available to run :'(
				break;
			}

			$success = false;

			if ( $jobObj instanceof \SmashPig\Core\Jobs\RunnableJob ) {
				try {
					if ( $jobObj->execute() ) {
						$success = true;
					} else {
						Logger::info( "Job tells us that it did not successfully execute. Sending to damaged message queue." );
					}
				} catch ( SmashPigException $ex ) {
					Logger::error( "Job threw exception. Sending to damaged message queue.", null, $ex );
				}
			} else {
				Logger::warning(
					get_class( $jobObj ) . " is not an instance of RunnableJob. Could not execute and sending to damaged message queue."
				);
			}
			if ( $success ) {
				$successCount += 1;
			} else {
				$this->damagedDatastore->queueAddObject( $jobObj );
			}

			$this->datastore->queueAckObject();

		} while (
			( ( time() - $startTime ) < $this->getOption( 'time-limit' ) ) &&
			( ++$messageCount < $this->getOption( 'max-messages' ) )
		);

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Processed $messageCount ($successCount successful) jobs in $elapsedTime seconds."
		);
	}

}

require( RUN_MAINTENANCE_IF_MAIN );
