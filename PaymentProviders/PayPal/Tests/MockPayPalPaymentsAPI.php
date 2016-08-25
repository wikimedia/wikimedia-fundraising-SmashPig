<?php namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\PaymentProviders\PayPal\PayPalPaymentsAPI;

class MockPayPalPaymentsAPI extends PayPalPaymentsAPI {
	protected function curl () {
		return 'VERIFIED';
	}
}
