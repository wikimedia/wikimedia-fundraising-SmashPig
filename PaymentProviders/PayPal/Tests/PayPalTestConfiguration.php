<?php
namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\Core\Configuration;

class PayPalTestConfiguration extends Configuration {

	public static function get () {
		return self::createForViewWithOverrideFile(
			'paypal',
			__DIR__ . '/config_test.yaml'
		);
	}

}
