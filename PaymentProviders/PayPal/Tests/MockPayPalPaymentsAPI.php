<?php namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\PaymentProviders\PayPal\PayPalPaymentsAPI;

class MockPayPalPaymentsAPI extends PayPalPaymentsAPI {
	protected function curl ( $ch, $post_fields ) {
		if ( CaptureIncomingMessageTest::$fail_verification ) {
			return 'INVALID';
		}
		return 'VERIFIED';
	}
}
