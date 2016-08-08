<?php namespace SmashPig\Core\Jobs;

use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;

/**
 * Job that deletes donor information from the pending data stores.
 * Used when we get a notification of payment failure.
 */
class DeletePendingJob extends RunnableJob {

	protected $orderId;
	protected $gateway;

	/**
	 * @param string $gateway Gateway identifier
	 * @param string $orderId Payment order ID
	 * @param string $correlationId Message correlation ID (deprecated)
	 */
	public function __construct( $gateway, $orderId, $correlationId ) {
		$this->gateway = $gateway;
		$this->orderId = $orderId;
		$this->correlationId = $correlationId;
	}

	public function execute() {
		$logger = Logger::getTaggedLogger( "corr_id-{$this->correlationId}" );
		$logger->info(
			"Deleting from pending queue where correlation ID='{$this->correlationId}'"
		);
		$pendingQueueObj = Configuration::getDefaultConfig()->object( 'data-store/pending' );
		$pendingQueueObj->removeObjectsById( $this->correlationId );

		$logger->info(
			"Deleting message from pending db where gateway = '{$this->gateway}' " .
			"and order ID='{$this->orderId}'"
		);
		$db = PendingDatabase::get();
		if ( $db ) {
			$dbMessage = $db->fetchMessageByGatewayOrderId(
				$this->gateway, $this->orderId
			);
			if ( $dbMessage ) {
				$db->deleteMessage( $dbMessage );
			}
		}
		return true;
	}
}
