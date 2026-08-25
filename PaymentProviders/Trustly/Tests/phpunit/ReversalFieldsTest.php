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

	/**
	 * ACH return codes other than R08/R10 (e.g. R03) are treated as reversals or reversal_reversals, if positive.
	 */
	public function testUnhandledRCodeIsTypedAsReversal(): void {
		$output = $this->processFile( 'P11KFUN-3618-r-code-reversal.csv' );

		$reversal = $this->findByType( $output, 'reversal' );
		$this->assertSame( '9400131071', $reversal['backend_processor_reversal_id'] );

		$reversed = $this->findByType( $output, 'reversal_reversed' );
		$this->assertSame( '9400131071', $reversed['backend_processor_txn_id'] );
		$this->assertArrayNotHasKey( 'backend_processor_reversal_id', $reversed );
	}

	/**
	 * Test that an AC118 refund has a gateway of gravy.
	 *
	 * It may have a long (hashed) original_merchant_reference that is not a gravy id
	 * but still need to be matched with a gravy transaction. Seen in real data:
	 * transaction_id 8090501261 (P11KFUN-3618-20260208120000-20260209120000-0001of0001.csv)
	 * is a genuinely gravy capture with a normal short reference, but its own AC118 refund -
	 * transaction_id 8094565296, original_transaction_id 8090501261
	 * (P11KFUN-3618-20260216120000-20260217120000-0001of0001.csv) - carries a long 64-char
	 * hash reference instead.
	 */
	public function testRefundWithLongMerchantReferenceKeepsGravyGateway(): void {
		$output = $this->processFile( 'P11KFUN-3618-refund-long-merchant-reference.csv' );
		$refund = $this->findByType( $output, 'refund' );

		$this->assertSame( 'gravy', $refund['gateway'] );
		$this->assertSame( '9500000000', $refund['backend_processor_parent_id'] );
	}

	/**
	 * Both legs of an unhandled R-code event (e.g. R03) are treated as gravy
	 * as we do not know for sure. Confirmed in real data: transaction_id
	 * 8206407324 (original_transaction_id 8049361922,
	 * P11KFUN-3618-20260811120000-20260812120000-0001of0001.csv), an R01 ACH
	 * return, is a genuinely gravy transaction per the real IPN log
	 * (public/audit/trustly/incoming/logs/fundraising-misc-20260807.gz.txt:
	 * gateway_txn_id 6bef478f-f60d-4b37-ac06-84fb176f45a2,
	 * payment_orchestrator_reconciliation_id 3HfWrU84VLlXcqGKxdCisk) despite
	 * the R-code reason.
	 */
	public function testRCodeReversalReportsGravyGatewayOnBothLegs(): void {
		$output = $this->processFile( 'P11KFUN-3618-r-code-reversal.csv' );

		$reversal = $this->findByType( $output, 'reversal' );
		$this->assertSame( 'gravy', $reversal['gateway'] );
		$this->assertSame( '9400130000', $reversal['backend_processor_parent_id'] );
		$this->assertArrayNotHasKey( 'gateway_parent_id', $reversal );

		$reversed = $this->findByType( $output, 'reversal_reversed' );
		$this->assertSame( 'gravy', $reversed['gateway'] );
	}
}
