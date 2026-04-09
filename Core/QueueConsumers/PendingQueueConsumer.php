<?php namespace SmashPig\Core\QueueConsumers;

use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;

class PendingQueueConsumer extends BaseQueueConsumer {

	/**
	 * @var PendingDatabase
	 */
	protected $pendingDatabase;

	/**
	 * @var PaymentsInitialDatabase
	 */
	protected $paymentsInitialDatabase;

	public function __construct( string $queueName, int $timeLimit, int $messageLimit, bool $waitForNewMessages ) {
		parent::__construct( $queueName, $timeLimit, $messageLimit, $waitForNewMessages );
		$this->pendingDatabase = PendingDatabase::get();
		$this->paymentsInitialDatabase = PaymentsInitialDatabase::get();
	}

	public function processMessage( array $message ) {
		// Some messages (e.g. recurring charges from CiviCRM) arrive with
		// invoice_id but no order_id. We need order_id for the pending DB
		// index so that RecordCaptureJob can look up the message later.
		if ( !isset( $message['order_id'] ) && isset( $message['invoice_id'] ) ) {
			$message['order_id'] = $message['invoice_id'];
		}
		$logIdentifier = "message with gateway '{$message['gateway']}'," .
			" order ID '" . ( $message['order_id'] ?? 'N/A' ) . "', and" .
			( isset( $message['gateway_txn_id'] ) ?
				" gateway txn id '{$message['gateway_txn_id']}'" :
				'no gateway txn id'
			);

		if ( $this->paymentsInitialDatabase->isTransactionFailed( $message ) ) {
			// Throw the message out if it's already failed
			Logger::info( "Skipping failed $logIdentifier" );
		} else {
			Logger::info( "Storing $logIdentifier in database" );
			$this->pendingDatabase->storeMessage( $message );
		}
	}
}
