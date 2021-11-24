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
	 *
	 * @param string $gatewayName
	 * @param string $orderId
	 * @param bool $withBreakdown
	 * @return array|null Fraud record for a transaction, or null if nothing matches
	 *  If $withBreakdown is requested, the array will contain a 'score_breakdown' key
	 *  whose value is an array whose keys are filter_names and values are risk_scores
	 * @throws DataStoreException
	 */
	public function fetchMessageByGatewayOrderId(
		string $gatewayName,
		string $orderId,
		bool $withBreakdown = false
	): ?array {
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
		if ( $withBreakdown ) {
			$row['score_breakdown'] = [];
			$sql = 'SELECT filter_name, risk_score FROM payments_fraud_breakdown
				WHERE payments_fraud_id = :payments_fraud_id';
			$params = [ 'payments_fraud_id' => $row['id'] ];
			$executed = $this->prepareAndExecute( $sql, $params );
			while ( $breakdownRow = $executed->fetch( PDO::FETCH_ASSOC ) ) {
				$row['score_breakdown'][$breakdownRow['filter_name']] = $breakdownRow['risk_score'];
			}
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

	protected function getTableScriptFiles(): array {
		return [
			'005_CreatePaymentsFraudTable.sql',
			'006_CreatePaymentsFraudBreakdownTable.sql'
		];
	}
}
