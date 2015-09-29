<?php
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\RefundCompleted;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted;

class NormalizeTest extends BaseSmashPigUnitTestCase {
	function setUp() {
		parent::setUp();
		$this->captureCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/CaptureCompleted.json" );
		$this->captureDeclined = $this->loadJson( __DIR__ . "/../Data/IPN/CaptureDeclined.json" );
		$this->refundCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/RefundCompleted.json" );
		$this->refundDeclined = $this->loadJson( __DIR__ . "/../Data/IPN/RefundDeclined.json" );
	}

	function normalizedToArray( $message ) {
		return json_decode( $message->toJson(), true );
	}

	function testNormalizeCaptureCompleted() {
		$expected = array(
			'completion_message_id' => 'amazon-98765432-1',
			'contribution_tracking_id' => '98765432',
			'correlationId' => 'amazon-P01-0000000-0000000-000000',
			'currency' => 'USD',
			'date' => 1357002061,
			'fee' => '0.0',
			'gateway' => 'amazon',
			'gateway_status' => 'Completed',
			'gateway_txn_id' => 'P01-0000000-0000000-000000',
			'gross' => '10.0',
			'payment_method' => 'amazon',
		);
		$stripFields = array(
			'propertiesExportedAsKeys',
			'propertiesExcludedFromExport',
		);
		$message = new CaptureCompleted( $this->captureCompleted );
		$normalized = $this->normalizedToArray( $message->normalizeForQueue() );
		foreach ( $stripFields as $field ) {
			unset( $normalized[$field] );
		}
		$this->assertEquals( $expected, $normalized );
	}

	function testNormalizeRefundCompleted() {
		$expected = array(
			'correlationId' => 'amazon-P01-0000000-0000000-R00000',
			'gross_currency' => 'USD',
			'date' => 1357002061,
			'gateway' => 'amazon',
			'gateway_parent_id' => 'P01-0000000-0000000-C00000',
			'gateway_refund_id' => 'P01-0000000-0000000-R00000',
			'gross' => '10.0',
			'gross_currency' => 'USD',
			'type' => 'refund',
		);
		$stripFields = array(
			'propertiesExportedAsKeys',
			'propertiesExcludedFromExport',
		);
		$message = new RefundCompleted( $this->refundCompleted );
		$normalized = $this->normalizedToArray( $message->normalizeForQueue() );
		foreach ( $stripFields as $field ) {
			unset( $normalized[$field] );
		}
		$this->assertEquals( $expected, $normalized );
	}
}