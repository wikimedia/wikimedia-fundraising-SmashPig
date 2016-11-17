<?php
namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\PaymentProviders\Amazon\Actions\ReconstructMerchantReference;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted;

class ActionsTest extends AmazonTestCase {

	public function testReconstructMerchantId() {
		$captureCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/CaptureCompleted.json" );
		$captureCompleted["CaptureDetails"]["CaptureReferenceId"] = 'AUTHORIZE_123456767';
		$message = new CaptureCompleted( $captureCompleted );
		$action = new ReconstructMerchantReference();
		$action->execute( $message );
		$this->assertEquals( '123456789-0', $message->getOrderId() );
	}
}
