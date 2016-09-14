<?php namespace SmashPig\Core\Jobs;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;

/**
 * Job that deletes donor information from the pending database.
 * Used when we get a notification of payment failure.
 */
class DeletePendingJob extends RunnableJob {

	protected $order_id;
	protected $gateway;

	/**
	 * @param string $gateway Gateway identifier
	 * @param string $orderId Payment order ID
	 * @param string $correlationId Message correlation ID (deprecated)
	 * @return DeletePendingJob
	 */
	public static function factory( $gateway, $orderId, $correlationId ) {
		$job = new DeletePendingJob();

		$job->gateway = $gateway;
		$job->order_id = $orderId;
		$job->correlationId = $correlationId;

		return $job;
	}

	public function execute() {
		$logger = Logger::getTaggedLogger(
			"corr_id-{$this->gateway}-{$this->order_id}"
		);

		$logger->info(
			"Deleting message from pending db where gateway = '{$this->gateway}' " .
			"and order ID='{$this->order_id}'"
		);

		$deleteParams = array(
			'gateway' => $this->gateway,
			'order_id' => $this->order_id,
		);
		PendingDatabase::get()
			->deleteMessage( $deleteParams );

		return true;
	}
}
