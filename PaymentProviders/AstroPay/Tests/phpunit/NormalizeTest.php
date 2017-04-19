<?php
namespace SmashPig\PaymentProviders\AstroPay\Test;

use SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages\PaymentMessage;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class NormalizeTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var array
	 */
	protected $paymentSuccess;

	public function setUp() {
		parent::setUp();
		$this->paymentSuccess = $this->loadJson( __DIR__ . "/../Data/paid.json" );
	}

	/**
	 * Check that we produce the right message, including the completion id
	 */
	public function testNormalizePaymentSuccess() {
		$expected = array(
			'completion_message_id' => 'astropay-32303.1',
			'contribution_tracking_id' => '32303',
			'currency' => 'BRL',
			'gateway' => 'astropay',
			'gateway_status' => '9',
			'gateway_txn_id' => '31912',
			'gross' => '100.00',
			'order_id' => '32303.1',
		);
		$stripFields = array(
			'propertiesExportedAsKeys',
			'propertiesExcludedFromExport',
			'date',
		);
		$message = new PaymentMessage();
		$message->constructFromValues( $this->paymentSuccess );
		$normalized = $message->normalizeForQueue();
		foreach ( $stripFields as $field ) {
			unset( $normalized[$field] );
		}
		$this->assertEquals( $expected, $normalized );
	}
}
