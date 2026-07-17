<?php

namespace SmashPig\PaymentProviders\CheckoutCom\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\CheckoutCom\Audit\CheckoutComAudit;

class AuditTest extends TestCase {

	public function testProcessSettlementBreakdownDonation(): void {
		$processor = new CheckoutComAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement-breakdown_ent_testcheckoutfixture_20260702_00000003k599_1.csv' );

		$actual = $output[0];
		$expected = [
			'gateway' => 'gravy',
			'audit_file_gateway' => 'checkoutcom',
			'backend_processor' => 'checkoutcom',
			'gateway_account' => 'Example donation channel',
			'gateway_txn_id' => '922d7c4b-9181-4fe9-b5e8-cb3f8c0883c1',
			'backend_processor_txn_id' => 'pay_test_charge_001',
			'auth_id' => 'pay_test_charge_001',
			'payment_orchestrator_reconciliation_id' => '4RpfKjZxqXWKsUOeHaVteD',
			'settlement_batch_reference' => '00000003K599',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'date' => 1782900000,
			'settled_date' => 1782950400,
			'exchange_rate' => 1.0,
			'type' => 'donation',
			'gross' => '50.00',
			'currency' => 'USD',
			'original_currency' => 'USD',
			'settled_currency' => 'USD',
			'original_total_amount' => '50.00',
			'original_fee_amount' => '-1.50',
			'original_net_amount' => '48.50',
			'settled_fee_amount' => '-1.50',
			'settled_net_amount' => '48.50',
			'settled_total_amount' => '50.00',
		];
		$this->assertEquals( $expected, $actual );
	}

	public function testProcessSettlementBreakdownFee(): void {
		$processor = new CheckoutComAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement-breakdown_ent_testcheckoutfixture_20260702_00000003k599_1.csv' );

		$this->assertEquals( [
			'gateway' => 'checkoutcom',
			'audit_file_gateway' => 'checkoutcom',
			'type' => 'fee',
			'gateway_txn_id' => 'fee-pay_test_fee_0011782910800Charge',
			'gateway_account' => 'Example donation channel',
			'settlement_batch_reference' => '00000003K599',
			'date' => 1782910800,
			'settled_date' => 1782950400,
			'settled_currency' => 'USD',
			'settled_total_amount' => '0.00',
			'settled_fee_amount' => '-0.25',
			'settled_net_amount' => '-0.25',
		], $output[3] );
	}

	public function testProcessSettlementBreakdownPayout(): void {
		$processor = new CheckoutComAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement-breakdown_ent_testcheckoutfixture_20260702_00000003k599_1.csv' );

		$payout = end( $output );
		$this->assertSame( 'payout', $payout['type'] );
		$this->assertSame( '79.50', $payout['settled_total_amount'] );
		$this->assertSame( '00000003K599', $payout['settlement_batch_reference'] );
		$this->assertSame( '00000003K599', $payout['gateway_txn_id'] );
	}
}
