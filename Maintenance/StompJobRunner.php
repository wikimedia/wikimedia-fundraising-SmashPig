<?php namespace SmashPig\Maintenance;

require( 'MaintenanceBase.php' );

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\StompDataStore;

$maintClass = '\SmashPig\Maintenance\StompJobRunner';

/**
 * The job runner script reads job requests from a specified queue and dispatches the requests.
 * It attempts to time limit itself; however a long running job will not be terminated. Rather
 * after it completes no new jobs will be dispatched and this script will exit.
 */
class StompJobRunner extends MaintenanceBase {

	protected $datastore = null;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to consume from', 'jobs' );
		$this->addOption( 'time-limit', 'Try to keep execution under <n> seconds', 60, 't' );
		$this->addOption( 'max-messages', 'At most consume <n> messages', 10, 'm' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$this->datastore = new StompDataStore( $this->getOption( 'queue' ) );

		$startTime = time();
		$messageCount = 0;
		$successCount = 0;

		do {
			$jobObj = $this->datastore->queueGetObject();

			if ( !$jobObj ) {
				// No more jobs available to run :'(
				break;
			}

			if ( $jobObj instanceof \SmashPig\Core\Jobs\RunnableJob ) {
				if ( $jobObj->execute() ) {
					$successCount += 1;
					$this->datastore->queueAckObject();
				} else {
					Logger::info( "Job tells us that it did not successfully execute. Re-queueing for later.", $jobObj );
					$this->datastore->queueIgnoreObject();
				}
			} else {
				$this->datastore->queueIgnoreObject();
				Logger::warning(
					get_class( $jobObj ) . " is not an instance of RunnableJob. Could not execute and re-queueing."
				);
			}

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
