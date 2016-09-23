<?php
namespace SmashPig\Core\DataStores;

use PDO;

/**
 * Data store containing finalized messages.
 */
class PaymentsInitialDatabase extends SmashPigDatabase {

	/**
	 * Return true if the message already exists in the payments-init table,
	 * and has a very final status indicating we don't expect to do any more
	 * processing.
	 *
	 * @param array $message Payments initial message
	 *	FIXME: Or pass ID parameters explicitly and call this
	 *	isTransactionFinalizedByGatewayOrderId??
	 * @return boolean
	 */
	public function isTransactionFinalized( $message ) {
		$message = $this->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );
		if ( $message === null ) {
			return false;
		}
		if ( in_array( $message['payments_final_status'], array( 'failed', 'complete' ) ) ) {
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
		$prepared = self::$db->prepare( '
			select * from payments_initial
			where gateway = :gateway
				and order_id = :order_id
			limit 1' );
		$prepared->bindValue( ':gateway', $gatewayName, PDO::PARAM_STR );
		$prepared->bindValue( ':order_id', $orderId, PDO::PARAM_STR );
		$prepared->execute();
		$row = $prepared->fetch( PDO::FETCH_ASSOC );
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
