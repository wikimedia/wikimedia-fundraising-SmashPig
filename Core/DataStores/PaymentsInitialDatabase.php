<?php
namespace SmashPig\Core\DataStores;

use PDO;

/**
 * Data store containing finalized messages.
 */
class PaymentsInitialDatabase extends SmashPigDatabase {

	/**
	 * Return true if the message already exists in the payments-init table,
	 * and is marked as having failed.
	 *
	 * @param array $message Payments initial message
	 *	FIXME: Or pass ID parameters explicitly and call this
	 *	isTransactionFinalizedByGatewayOrderId??
	 * @return boolean
	 */
	public function isTransactionFailed( $message ) {
		$message = $this->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );
		if ( $message === null ) {
			return false;
		}
		if ( $message['payments_final_status'] === 'failed' ) {
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
		$params = array(
			'gateway' => $gatewayName,
			'order_id' => $orderId,
		);
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
	}

	protected function getConfigKey() {
		return 'data-store/fredge-db';
	}

	protected function getTableScriptFile() {
		return '003_CreatePaymentsInitialTable.sql';
	}
}
