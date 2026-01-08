<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Test;

require_once 'AuditTest.php';

/**
 * Verify PayPal audit file normalization functions
 *
 * @group PayPal
 * @group Audit
 */
class STLAuditTest extends AuditTest {

	public function testProcessFile(): void {
		$output = $this->processFile( 'stl.csv' );
		$this->assertCount( 6, $output, 'Should have found one row' );

		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'AUD',
			'exchange_rate' => 1,
			'settled_currency' => 'AUD',
			'date' => 1767686430,
			'gateway' => 'paypal',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => 52,
			'settled_fee_amount' => -2.25,
			'settled_net_amount' => 49.75,
			'gross' => 52,
			'fee' => 2.25,
			'original_fee_amount' => -2.25,
			'gateway_txn_id' => '5678',
			'settled_date' => strtotime( '2026/01/06 00:00:30 -0800' ),
			'contribution_tracking_id' => '2444',
			'order_id' => '2444.1',
			'settlement_batch_reference' => '20260106',
			'original_total_amount' => '52',
			'original_net_amount' => '49.75',
			'original_currency' => 'AUD',
		], $output[0] );

		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'GBP',
			'exchange_rate' => 1,
			'settled_currency' => 'GBP',
			'date' => 1767771099,
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => -1.34,
			'settled_net_amount' => -0.87,
			'settled_fee_amount' => 0.47,
			'original_total_amount' => '-1.34',
			'original_net_amount' => '-0.87',
			'original_currency' => 'GBP',
			'original_fee_amount' => 0.47,
			'gross' => 1.34,
			'fee' => 0.47,
			'gateway_txn_id' => '6899',
			'settled_date' => strtotime( '2026/01/06 23:31:39 -0800' ),
			'contribution_tracking_id' => 2216,
			'order_id' => '2216.15',
			'settlement_batch_reference' => '20260106',
			'type' => 'refund',
			'gateway_refund_id' => '6899',
			'gross_currency' => 'GBP',
			'gateway_parent_id' => '99900',
		], $output[3] );

		$this->assertEquals( [
			'settled_currency' => 'GBP',
			'settled_total_amount' => 7.40,
			'gateway' => 'paypal',
			'type' => 'payout',
			'gateway_txn_id' => '',
			'invoice_id' => '',
			'settlement_batch_reference' => '20260106',
			'settled_date' => 1767686400,
			'date' => 1767686400,
		], $output[4] );

		$this->assertEquals( [
			'settled_currency' => 'AUD',
			'settled_total_amount' => 54.35,
			'gateway' => 'paypal',
			'type' => 'payout',
			'gateway_txn_id' => '',
			'invoice_id' => '',
			'settlement_batch_reference' => '20260106',
			'settled_date' => 1767686400,
			'date' => 1767686400,
		], $output[5] );
	}

	public function testProcessConvertedCurrencyTransaction(): void {
		$output = $this->processFile( 'stl_currency_convert.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );

		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'BRL',
			'original_currency' => 'BRL',
			'settled_currency' => 'USD',
			'exchange_rate' => 0.17771739130434783,
			'date' => strtotime( '2026/01/06 04:26:31 -0800' ),
			'gateway' => 'gravy',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => '3.91',
			'settled_fee_amount' => '-0.64',
			'settled_net_amount' => '3.27',
			'gross' => 22,
			'fee' => 3.6,
			'original_total_amount' => 22,
			'original_fee_amount' => -3.6,
			'original_net_amount' => 18.4,
			'gateway_txn_id' => 'bc4ae813-a43e-4dd4-91b8-3576ca2d3804',
			'settled_date' => strtotime( '2026/01/06 04:26:31 -0800' ),
			'contribution_tracking_id' => 7233,
			'order_id' => '7233.6',
			'settlement_batch_reference' => '20260106',
			'backend_processor_txn_id' => '1DV3',
			'backend_processor' => 'paypal',
			'payment_orchestrator_reconciliation_id' => '5jImyEK1vFvvvmoxlWR7SO',

		], $output[0] );
	}
}
