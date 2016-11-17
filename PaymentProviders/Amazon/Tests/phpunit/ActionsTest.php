<?php
namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\PaymentProviders\Amazon\Actions\ReconstructMerchantReference;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted;

class ActionsTest extends AmazonTestCase {

	public function testReconstructMerchantId() {
		$captureCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/CaptureCompleted.json" );
		$captureCompleted["CaptureDetails"]["CaptureReferenceId"] = 'AUTHORIZE_123456767';
		$message = new CaptureCompleted( $captureCompleted );
		$this->assertEquals( 'AUTHORIZE_123456767', $message->getOrderId() );
		$action = new ReconstructMerchantReference();
		$action->execute( $message );
		// This ID comes from getOrderReferenceDetails.json
		$this->assertEquals( '123456789-0', $message->getOrderId() );
	}

	/**
	 * Don't waste API calls when it's not an AUTHORIZE_ id
	 */
	public function testReconstructMerchantIdNotNeeded() {
		$captureCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/CaptureCompleted.json" );
		$message = new CaptureCompleted( $captureCompleted );
		$action = new ReconstructMerchantReference();
		$action->execute( $message );
		$this->assertEquals( '98765432-1', $message->getOrderId() );
		$this->assertEmpty( $this->mockClient->calls );
	}
}
