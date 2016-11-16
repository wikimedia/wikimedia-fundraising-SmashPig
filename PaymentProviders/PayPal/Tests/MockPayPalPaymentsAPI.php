<?php namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\PaymentProviders\PayPal\PayPalPaymentsAPI;

class MockPayPalPaymentsAPI extends PayPalPaymentsAPI {
	protected function curl ( $ch, $post_fields ) {
		if ( CaptureIncomingMessageTest::$fail_verification ) {
			return 'INVALID';
		}
		if ( CaptureIncomingMessageTest::$paypal_is_broken ) {
			return 'lkjasjdhfiuasdgjgbasdd';
		}
		return 'VERIFIED';
	}
}
