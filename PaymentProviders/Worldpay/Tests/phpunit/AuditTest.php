<?php
namespace SmashPig\PaymentProviders\Worldpay\Test;

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Worldpay\Audit\WpgReconciliationFile;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Amazon audit file processor functions
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	public function setUp() {
		parent::setUp();

		$this->setConfig( null, 'Worldpay' );
	}

	public function testProcessDonation() {
		$processor = new WpgReconciliationFile();
		$output = $processor->parse( __DIR__ . '/../Data/audit/capture.txt' );
		$this->assertEquals( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'worldpay',
			'date' => 1443945114,
			'gross' => '10.00',
			'currency' => 'EUR',
			'gateway_txn_id' => '27943765.0',
			'payment_method' => 'cc',
			'payment_submethod' => 'mc',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	public function testProcessRefund() {
		$processor = new WpgReconciliationFile();
		$output = $processor->parse( __DIR__ . '/../Data/audit/refund.txt' );
		$this->assertEquals( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'worldpay',
			'date' => 1444033215,
			'gross' => '-1.20',
			'gateway_parent_id' => '27905662.1',
			'gross_currency' => 'EUR',
			'type' => 'refund',
			'gateway_refund_id' => '27905662.1',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

}
