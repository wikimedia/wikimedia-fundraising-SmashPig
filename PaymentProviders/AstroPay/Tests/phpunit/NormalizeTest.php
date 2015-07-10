<?php namespace SmashPig\PaymentProviders\AstroPay\Test;

use SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages\PaymentMessage;

class NormalizeTest extends \BaseSmashPigUnitTestCase {
	function setUp() {
		parent::setUp();
		$this->paymentSuccess = $this->loadJson( __DIR__ . "/../Data/paid.json" );
	}

	function normalizedToArray( $message ) {
		return json_decode( $message->toJson(), true );
	}

	/**
	 * Check that we produce the right message, including the completion id
	 */
	function testNormalizePaymentSuccess() {
		$expected = array(
			'completion_message_id' => 'astropay-32303.1',
			'contribution_tracking_id' => '32303',
			'correlationId' => 'astropay-31912',
			'currency' => 'BRL',
			'gateway' => 'astropay',
			'gateway_status' => '9',
			'gateway_txn_id' => '31912',
			'gross' => '100.00',
		);
		$stripFields = array(
			'propertiesExportedAsKeys',
			'propertiesExcludedFromExport',
			'date',
		);
		$message = new PaymentMessage();
		$message->constructFromValues( $this->paymentSuccess );
		$normalized = $this->normalizedToArray( $message->normalizeForQueue() );
		foreach ( $stripFields as $field ) {
			unset( $normalized[$field] );
		}
		$this->assertEquals( $expected, $normalized );
	}
}
