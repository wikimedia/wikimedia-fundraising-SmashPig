<?php
namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\Core\Context;
use SmashPig\Tests\BaseSmashPigUnitTestCase;
use SmashPig\PaymentProviders\Amazon\Audit\AmazonAudit;

/**
 * Verify Amazon audit file processor functions
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	public function setUp() {
		parent::setUp();
		$ctx = Context::get();
		$config = AmazonTestConfiguration::instance( $ctx->getGlobalConfiguration() );
		$ctx->setProviderConfiguration( $config );
	}

	/**
	 * Normal donation
	 */
	public function testProcessDonation() {
		$processor = new AmazonAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/audit/2015-10-01-SETTLEMENT_DATA_371273040777777.csv' );
		$this->assertEquals( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'amazon',
			'date' => 1443723034,
			'gross' => '10.00',
			'contribution_tracking_id' => '87654321',
			'currency' => 'USD',
			'gateway_txn_id' => 'P01-1488694-1234567-C034811',
			'log_id' => '87654321-0',
			'payment_method' => 'amazon',
			'fee' => '0.59',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new AmazonAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/audit/2015-10-06-REFUND_DATA_414749300022222.csv' );
		$this->assertEquals( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'amazon',
			'date' => 1444087249,
			'gross' => '1.00',
			'gateway_parent_id' => 'P01-4968629-7654321-C070794',
			'gross_currency' => 'USD',
			'type' => 'refund',
			'gateway_refund_id' => 'P01-4968629-7654321-R017571',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * And a chargeback
	 */
	public function testProcessChargeback() {
		$processor = new AmazonAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/audit/2015-10-06-REFUND_DATA_414749300033333.csv' );
		$this->assertEquals( 1, count( $output ), 'Should have found one chargeback' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'amazon',
			'date' => 1444087249,
			'gross' => '1.00',
			'gateway_parent_id' => 'P01-4968629-2345678-C070794',
			'gross_currency' => 'USD',
			'gateway_refund_id' => 'P01-4968629-2345678-R017571',
			'type' => 'chargeback',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse chargeback correctly' );
	}
}
