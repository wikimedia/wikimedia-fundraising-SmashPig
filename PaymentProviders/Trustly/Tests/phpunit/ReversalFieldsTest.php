<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\Trustly\Test;

require_once 'AuditTestBase.php';

/**
 * Verify how SettlementFileParser normalizes chargebacks and refunds.
 *
 * @group Trustly
 * @group Audit
 */
class ReversalFieldsTest extends AuditTestBase {

	private function findByType( array $rows, string $type ): array {
		foreach ( $rows as $row ) {
			if ( ( $row['type'] ?? null ) === $type ) {
				return $row;
			}
		}
		$this->fail( "No row of type '$type' found in output" );
	}

	/**
	 * For non-reversal rows, getGatewayTxnId() returns the decoded UUID
	 * (Base62Helper::toUuid of original_merchant_reference). Chargebacks and
	 * refunds must follow the same contract instead of returning the raw
	 * Trustly transaction_id: downstream, AuditMessage::getContributionRecurID()
	 * compares this field against ContributionRecur.trxn_id, which is stored
	 * as the UUID - so a raw numeric id here can never match, silently
	 * defeating the recurring-series safeguard that's supposed to strip
	 * gateway_txn_id/payment_orchestrator_reconciliation_id in that case.
	 */
	public function testChargebackGatewayTxnIdIsTheDecodedUuidNotTheRawTrustlyId(): void {
		$output = $this->processFile( 'P11KFUN-3618-recurring-series-collision.csv' );
		$chargeback = $this->findByType( $output, 'chargeback' );

		$this->assertSame(
			$chargeback['gateway_parent_id'],
			$chargeback['gateway_txn_id'],
			'gateway_txn_id must be the decoded UUID, same as gateway_parent_id - not the raw ' .
			'backend_processor_txn_id ("9100000003")'
		);
		$this->assertNotSame( '9100000003', $chargeback['gateway_txn_id'] );
	}

	/**
	 * Test that our backend processor identifier bubbles up.
	 */
	public function testChargebackRefundHasBackendProcessorReversal(): void {
		$output = $this->processFile( 'P11KFUN-3618-recurring-series-collision.csv' );
		$chargeback = $this->findByType( $output, 'chargeback' );
		$this->assertSame( '9100000003', $chargeback['backend_processor_reversal_id'] );
	}
}
