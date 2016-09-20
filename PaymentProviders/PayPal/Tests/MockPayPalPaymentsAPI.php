<?php namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\PaymentProviders\PayPal\PayPalPaymentsAPI;

class MockPayPalPaymentsAPI extends PayPalPaymentsAPI {
	protected function curl ( $ch, $post_fields ) {
		// XXX Not sure if too twisted.
		if ( CaptureIncomingMessageTest::$verified_msg === $post_fields ) {
			return 'VERIFIED';
		}
	}
}
