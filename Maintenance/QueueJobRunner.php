<?php namespace SmashPig\Maintenance;

require ( 'MaintenanceBase.php' );

use RuntimeException;

use PHPQueue\Interfaces\FifoQueueStore;

use SmashPig\Core\Context;
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

	/**
	 * @var FifoQueueStore
	 */
	protected $damagedQueue = null;

	protected $successCount = 0;

	public function __construct() {
		parent::__construct();

		$this->addOption( 'queue', 'queue name to consume from', 'pending' );
		$this->addOption( 'damaged-queue', 'name of queue to hold failed job messages', 'pending-damaged' );

		// Get some defaults from configuration
		$basePath = 'maintenance/job-runner/';

		$this->addOption(
			'time-limit',
			'Try to keep execution under <n> seconds',
			$basePath . 'time-limit',
			't'
		);

		$this->addOption(
			'max-messages',
			'At most consume <n> messages',
			$basePath . 'message-limit',
			'm'
		);
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$consumer = new QueueConsumer(
			$this->getOption( 'queue' ),
			array( $this, 'runJob' ),
			$this->getOption( 'time-limit' ),
			$this->getOption( 'message-limit' ),
			$this->getOption( 'damaged-queue' )
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
	 * the queue consumer's damaged message queue functionality to either
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
				. 'Could not execute and sending to damaged message queue.'
			);
		}

		if ( !$jobObj->execute() ) {
			throw new RuntimeException(
				'Job tells us that it did not successfully execute. '
				. 'Sending to damaged message queue.'
			);
		}

		$this->successCount += 1;
	}

}

require ( RUN_MAINTENANCE_IF_MAIN );
