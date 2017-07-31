<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use SmashPig\PaymentProviders\Ingenico\Audit\IngenicoAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Audit
 * @group Ingenico
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessDonation() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/donation.xml.gz' );
		$this->assertEquals( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'globalcollect', // TODO: switch to ingenico for Connect
			'gross' => 3.00,
			'contribution_tracking_id' => '5551212',
			'currency' => 'USD',
			'order_id' => '987654321',
			'gateway_txn_id' => '987654321',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'date' => 1501368968,
			'user_ip' => '111.222.33.44',
			'first_name' => 'Arthur',
			'last_name' => 'Aardvark',
			'street_address' => '1111 Fake St',
			'city' => 'Denver',
			'country' => 'US',
			'email' => 'dutchman@flying.net',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/refund.xml.gz' );
		$this->assertEquals( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'globalcollect', // TODO: switch to ingenico for Connect
			'contribution_tracking_id' => '5551212',
			'date' => 1500942220,
			'gross' => 100,
			'gateway_parent_id' => '123456789',
			'gross_currency' => 'USD',
			'type' => 'refund',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * And a chargeback
	 */
	public function testProcessChargeback() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/chargeback.xml.gz' );
		$this->assertEquals( 1, count( $output ), 'Should have found one chargeback' );
		$actual = $output[0];
		$expected = array(
			'gateway' => 'globalcollect', // TODO: switch to ingenico for Connect
			'contribution_tracking_id' => '5551212',
			'date' => 1495023569,
			'gross' => 200,
			'gateway_parent_id' => '5167046621',
			'gross_currency' => 'USD',
			'type' => 'chargeback',
		);
		$this->assertEquals( $expected, $actual, 'Did not parse chargeback correctly' );
	}
}
