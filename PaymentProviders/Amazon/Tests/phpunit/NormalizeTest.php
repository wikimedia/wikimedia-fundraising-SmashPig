<?php

use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\SubscriptionPaymentSuccess;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\SubscriptionSuccessful;

class NormalizeTest extends BaseSmashPigUnitTestCase {
	function setUp() {
		parent::setUp();
		$this->subscriptionPaymentSuccess = $this->loadJson( __DIR__ . "/../Data/IPN/SubscriptionPaymentSuccess.json" );
		$this->subscriptionSuccessful = $this->loadJson( __DIR__ . "/../Data/IPN/SubscriptionSuccessful.json" );
	}

	function normalizedToArray( $message ) {
		return json_decode( $message->toJson(), true );
	}

	function testNormalizeSubscriptionPaymentSuccess() {
		$expected = array(
			'city' => '',
			'contribution_tracking_id' => '450',
			'correlationId' => 'amazon-17PJULZUA5ZLH6A184D2T6ST5NTBAQ18165',
			'country' => '',
			'currency' => 'USD',
			'date' => '1367543022',
			'email' => 'foo@d.net',
			'gateway' => 'amazon',
			'gateway_status' => 'PS',
			'gateway_txn_id' => '17PJULZUA5ZLH6A184D2T6ST5NTBAQ18165',
			'gross' => '1.00',
			'last_name' => 'bart spanger',
			'postal_code' => '',
			'recurring' => 1,
			'state_province' => '',
			'street_address' => '',
			'subscr_id' => 'f138980f-90ca-43ba-9421-41a4996779ea',
			'supplemental_address_1' => '',
			'txn_type' => 'subscr_payment',
		);
		$stripFields = array(
			'propertiesExportedAsKeys',
			'propertiesExcludedFromExport',
		);
		$message = new SubscriptionPaymentSuccess();
		$message->constructFromValues( $this->subscriptionPaymentSuccess );
		$normalized = $this->normalizedToArray( $message->normalizeForQueue() );
		foreach ( $stripFields as $field ) {
			unset( $normalized[$field] );
		}
		$this->assertEquals( $expected, $normalized );
	}

	function testNormalizeSubscriptionSuccessful() {
		$expected = array(
			'city' => '',
			'contribution_tracking_id' => '450',
			'correlationId' => 'amazon-f138980f-90ca-43ba-9421-41a4996779ea',
			'country' => '',
			'currency' => 'USD',
			'date' => '1367543021',
			'email' => 'foo@d.net',
			'frequency_interval' => 1,
			'frequency_unit' => 'month',
			'gateway' => 'amazon',
			'gateway_status' => 'SubscriptionSuccessful',
			'gross' => '1.00',
			'installments' => 0,
			'last_name' => 'bart spanger',
			'postal_code' => '',
			'recurring' => 1,
			'state_province' => '',
			'street_address' => '',
			'subscr_id' => 'f138980f-90ca-43ba-9421-41a4996779ea',
			'supplemental_address_1' => '',
			'txn_type' => 'subscr_signup',
		);
		$stripFields = array(
			'propertiesExportedAsKeys',
			'propertiesExcludedFromExport',
		);
		$message = new SubscriptionSuccessful();
		$message->constructFromValues( $this->subscriptionSuccessful );
		$normalized = $this->normalizedToArray( $message->normalizeForQueue() );
		foreach ( $stripFields as $field ) {
			unset( $normalized[$field] );
		}
		$this->assertEquals( $expected, $normalized );
	}
}
