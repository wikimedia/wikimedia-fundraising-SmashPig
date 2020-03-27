<?php
namespace SmashPig\Core\DataStores;

use PDO;

/**
 * Data store containing fraud scores
 * TODO: this class is only suitable for reading from the payments_fraud
 *  table until we implement inserts into payments_fraud_breakdown
 */
class PaymentsFraudDatabase extends SmashPigDatabase {

	/**
	 * Return fraud record for a (gateway, order_id), or null if none is found
	 * TODO: option to also return score breakdown
	 *
	 * @param string $gatewayName
	 * @param string $orderId
	 * @return array|null Fraud record for a transaction, or null if nothing matches
	 * @throws DataStoreException
	 */
	public function fetchMessageByGatewayOrderId(
		string $gatewayName,
		string $orderId
	) {
		$sql = 'SELECT * FROM payments_fraud
			WHERE gateway = :gateway
		    AND order_id = :order_id
			LIMIT 1';
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

	/**
	 * FIXME: only good for tests as is, need to insert breakdown rows
	 *
	 * @param array $message
	 * @throws DataStoreException
	 */
	public function storeMessage( array $message ) {
		list( $fieldList, $paramList ) = self::formatInsertParameters(
			$message
		);

		$sql = "INSERT INTO payments_fraud ( $fieldList ) VALUES ( $paramList )";
		$this->prepareAndExecute( $sql, $message );
	}

	protected function getConfigKey(): string {
		return 'data-store/fredge-db';
	}

	protected function getTableScriptFile(): string {
		return '005_CreatePaymentsFraudTable.sql';
	}
}
