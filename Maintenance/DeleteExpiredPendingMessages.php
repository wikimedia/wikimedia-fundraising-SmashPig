<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\UtcDate;

$maintClass = '\SmashPig\Maintenance\DeleteExpiredPendingMessages';

/**
 * Deletes old messages from the pending table
 */
class DeleteExpiredPendingMessages extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'gateway', 'gateway to delete messages for' );
		$this->addOption( 'days', 'age in days of oldest messages to keep', 30 );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$pendingDatabase = PendingDatabase::get();
		$gateway = $this->getOption( 'gateway' );
		$days = $this->getOption( 'days' );
		$deleteBefore = UtcDate::getUtcTimestamp( "-$days days" );

		$startTime = time();
		$deleted = $pendingDatabase->deleteOldMessages( $deleteBefore, $gateway );

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Deleted $deleted pending messages in $elapsedTime seconds."
		);
	}
}

require RUN_MAINTENANCE_IF_MAIN;
