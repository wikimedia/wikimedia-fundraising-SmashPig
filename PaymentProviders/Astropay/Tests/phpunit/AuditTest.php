<?php namespace SmashPig\PaymentProviders\Astropay\Test;

use SmashPig\PaymentProviders\Astropay\Audit\AstropayAudit;

/**
 * Verify Astropay audit file processor functions
 */
class AuditTest extends \BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessDonation() {
		$processor = new AstropayAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/donation.csv' );
		$this->assertEquals( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'astropay',
			'date' => 1434450583,
			'gross' => '5.00',
			'contribution_tracking_id' => '266221341',
			'country' => 'BR',
			'currency' => 'BRL',
			'email' => 'donoriffic@example.org',
			'gateway_txn_id' => '5432123',
			'payment_method' => 'cc',
			'payment_submethod' => 'mc',
			'settled_date' => 1434456032,
			'settled_currency' => 'USD',
			'settled_fee' => '0.03',
			'settled_gross' => '1.50',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new AstropayAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/refund.csv' );
		$this->assertEquals( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'astropay',
			'date' => 1434747909,
			'gross' => '5.00',
			'gateway_parent_id' => '7654321',
			'gateway_refund_id' => 'refund:12345',
			'gross_currency' => 'BRL',
			'type' => 'refund',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}
}
