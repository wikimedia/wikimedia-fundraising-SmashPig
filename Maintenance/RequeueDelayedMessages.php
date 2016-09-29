<?php
namespace SmashPig\Maintenance;

require ( 'MaintenanceBase.php' );

use SmashPig\Core\Configuration;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\DamagedDatabase;

$maintClass = '\SmashPig\Maintenance\RequeueDelayedMessages';

/**
 * Requeues damaged messages that are ready for re-processing
 */
class RequeueDelayedMessages extends MaintenanceBase {

	/**
	 * @var DamagedDatabase
	 */
	protected $damagedDatabase;


	public function __construct() {
		parent::__construct();
		$this->addOption(
			'max-messages', 'At most requeue <n> messages', 500, 'm'
		);
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$this->damagedDatabase = DamagedDatabase::get();

		$messages = $this->damagedDatabase->fetchRetryMessages(
			$this->getOption( 'max-messages' )
		);
		$stats = array();
		$config = Configuration::getDefaultConfig();

		foreach( $messages as $message ) {
			$queueName = $message['original_queue'];
			// FIXME: getting it by alias, this will be annoying cos -new
			$queue = $config->object( "data-store/$queueName", true );
			unset( $message['original_queue'] );
			$queue->push( $message );
			$this->damagedDatabase->deleteMessage( $message );
			if ( isset( $stats[$queueName] ) ) {
				$stats[$queueName]++;
			} else {
				$stats[$queueName] = 1;
			}
		}

		foreach ( $stats as $queueName => $count ) {
			Logger::info(
				"Requeued $count messages to $queueName."
			);
		}
	}
}

require ( RUN_MAINTENANCE_IF_MAIN );
