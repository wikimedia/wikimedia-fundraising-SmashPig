<?php namespace SmashPig\Maintenance;

require ( 'MaintenanceBase.php' );

use RuntimeException;

use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\QueueConsumer;

$maintClass = '\SmashPig\Maintenance\QueueJobRunner';

/**
 * The job runner script reads job requests from a specified queue and dispatches the requests.
 * It attempts to time limit itself; however a long running job will not be terminated. Rather
 * after it completes no new jobs will be dispatched and this script will exit.
 */
class QueueJobRunner extends MaintenanceBase {

	protected $successCount = 0;

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

		// Get some defaults from configuration
		$basePath = 'maintenance/job-runner/';

		$consumer = new QueueConsumer(
			$this->getOption( 'queue' ),
			array( $this, 'runJob' ),
			$this->getOptionOrConfig( 'time-limit', $basePath . 'time-limit' ),
			$this->getOptionOrConfig( 'max-messages', $basePath . 'message-limit' )
		);

		$startTime = time();
		$messageCount = $consumer->dequeueMessages();

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Processed $messageCount ($this->successCount successful) jobs in $elapsedTime seconds."
		);
	}

	/**
	 * Instantiates and runs a job defined by a queue message. Depends on
	 * the queue consumer's damaged message store functionality to either
	 * divert messages or stop execution on bad message or job failure.
	 * @param array $jobMessage
	 * @throws \SmashPig\Core\DataStores\DataSerializationException
	 */
	public function runJob( $jobMessage ) {
		if ( !isset( $jobMessage['php-message-class'] ) ) {
			throw new RuntimeException(
				'Job message missing required key \'php-message-class\''
			);
		}

		$className = $jobMessage['php-message-class'];
		$jsonMessage = json_encode( $jobMessage );
		$jobObj = KeyedOpaqueStorableObject::fromJsonProxy( $className, $jsonMessage );

		if ( !( $jobObj instanceof RunnableJob ) ) {
			throw new RuntimeException(
				get_class( $jobObj ) . ' is not an instance of RunnableJob. '
				. 'Could not execute and sending to damaged message store.'
			);
		}

		if ( !$jobObj->execute() ) {
			throw new RuntimeException(
				'Job tells us that it did not successfully execute. '
				. 'Sending to damaged message store.'
			);
		}

		$this->successCount += 1;
	}

}

require ( RUN_MAINTENANCE_IF_MAIN );
