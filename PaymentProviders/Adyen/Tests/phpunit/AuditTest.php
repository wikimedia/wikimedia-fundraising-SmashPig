<?php
namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\PaymentProviders\Adyen\Audit\AdyenAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Adyen audit file processor functions
 *
 * @group Adyen
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessDonation() {
		$processor = new AdyenAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/donation.csv' );
		$this->assertEquals( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'adyen',
			'gross' => '1.00',
			'contribution_tracking_id' => '33992337',
			'currency' => 'USD',
			'gateway_txn_id' => '5364893193133131',
			'log_id' => '33992337.0',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa-debit',
			'date' => 1455840651,
			'settled_currency' => 'USD',
			'fee' => '0.24',
			'settled_gross' => '0.76',
			'settled_fee' => '0.24',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new AdyenAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/refund.csv' );
		$this->assertEquals( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'adyen',
			'contribution_tracking_id' => '92598312',
			'date' => 1455128736,
			'gross' => '1.00',
			'gateway_parent_id' => '4522268860022701',
			'gateway_refund_id' => '4522268869855336',
			'gross_currency' => 'USD',
			'log_id' => '92598312.0',
			'type' => 'refund',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * And a chargeback
	 */
	public function testProcessChargeback() {
		$processor = new AdyenAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/chargeback.csv' );
		$this->assertEquals( 1, count( $output ), 'Should have found one chargeback' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'adyen',
			'contribution_tracking_id' => '92598318',
			'date' => 1455128736,
			'gross' => '1.00',
			'gateway_parent_id' => '4555568860022701',
			'gateway_refund_id' => '4555568869855336',
			'gross_currency' => 'USD',
			'log_id' => '92598318.0',
			'type' => 'chargeback',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse chargeback correctly' );
	}
}
