<?php
namespace SmashPig\Core\DataStores;

use PDO;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;

/**
 * Data store containing finalized messages.
 */
class PaymentsInitialDatabase extends SmashPigDatabase {

	/**
	 * Return true if the message already exists in the payments-init table,
	 * is marked as having failed, and is not up for review.
	 *
	 * @param array $message Payments initial message
	 *	FIXME: Or pass ID parameters explicitly and call this
	 *	isTransactionFinalizedByGatewayOrderId??
	 * @return bool
	 */
	public function isTransactionFailed( $message ) {
		$message = $this->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );
		if ( $message === null ) {
			return false;
		}
		return self::isMessageFailed( $message );
	}

	/**
	 * @param array $message a payments-init message
	 * @return bool true if the message indicates that the payment has been
	 *  definitively failed and won't come up again
	 */
	public static function isMessageFailed( $message ) {
		if (
			(
				$message['payments_final_status'] === FinalStatus::FAILED ||
				$message['payments_final_status'] === FinalStatus::CANCELLED
			) &&
			$message['validation_action'] !== ValidationAction::REVIEW
		) {
			return true;
		}
		return false;
	}

	/**
	 * Return record matching a (gateway, order_id), or null if none is found
	 *
	 * @param $gatewayName string
	 * @param $orderId string
	 * @return array|null Record related to a transaction, or null if nothing matches
	 */
	public function fetchMessageByGatewayOrderId( $gatewayName, $orderId ) {
		$sql = 'select * from payments_initial
			where gateway = :gateway
				and order_id = :order_id
			limit 1';
		$params = [
			'gateway' => $gatewayName,
			'order_id' => $orderId,
		];
		$executed = $this->prepareAndExecute( $sql, $params );
		$row = $executed->fetch( PDO::FETCH_ASSOC );
		if ( !$row ) {
			return null;
		}
		return $row;
	}

	public function storeMessage( $message ) {
		list( $fieldList, $paramList ) = self::formatInsertParameters(
			$message
		);

		$sql = "INSERT INTO payments_initial ( $fieldList ) VALUES ( $paramList )";
		$this->prepareAndExecute( $sql, $message );

		return $this->getDatabase()->lastInsertId();
	}

	protected function getConfigKey() {
		return 'data-store/fredge-db';
	}

	protected function getTableScriptFile() {
		return '003_CreatePaymentsInitialTable.sql';
	}
}
