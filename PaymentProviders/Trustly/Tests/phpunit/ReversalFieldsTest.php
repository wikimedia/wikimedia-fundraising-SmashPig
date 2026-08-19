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
	 * The reversal_reversed Sale leg should report gateway 'trustly' - that's
	 * what isGravy()'s early return exists for (T434916). But an AC118 refund
	 * with a long (hashed) original_merchant_reference also fails isGravy()
	 * for an unrelated reason, and must still report gateway 'gravy': CRM-side
	 * matching (AuditMessage::getExistingContribution()) only tries the
	 * backend_processor_txn_id fallback lookup when the raw gateway is
	 * 'gravy', which is how these refunds - lacking a real gravy gateway_txn_id
	 * match - get linked to their parent contribution at all.
	 */
	public function testRefundWithLongMerchantReferenceKeepsGravyGateway(): void {
		$output = $this->processFile( 'P11KFUN-3618-refund-long-merchant-reference.csv' );
		$refund = $this->findByType( $output, 'refund' );

		$this->assertSame( 'gravy', $refund['gateway'] );
		$this->assertSame( '9500000000', $refund['backend_processor_parent_id'] );
	}

	/**
	 * Both legs of an unhandled R-code event (e.g. R03) are a pure Trustly ACH
	 * bank return, not a gravy transaction. The Return leg's
	 * original_merchant_reference is often short, which would otherwise pass
	 * isGravy()'s heuristic and (wrongly) report gateway 'gravy' with a
	 * fabricated gateway_parent_id/gateway_refund_id - causing CRM-side
	 * matching to find the wrong (original) contribution instead of an
	 * already-recorded reversal that only carries a backend_processor
	 * identifier. See T434916.
	 */
	public function testRCodeReversalReportsTrustlyGatewayOnBothLegs(): void {
		$output = $this->processFile( 'P11KFUN-3618-r-code-reversal.csv' );

		$reversal = $this->findByType( $output, 'reversal' );
		$this->assertSame( 'trustly', $reversal['gateway'] );
		$this->assertSame( '9400130000', $reversal['backend_processor_parent_id'] );
		$this->assertArrayNotHasKey( 'gateway_parent_id', $reversal );

		$reversed = $this->findByType( $output, 'reversal_reversed' );
		$this->assertSame( 'trustly', $reversed['gateway'] );
	}
}
