<?php
namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\Tests\TestingProviderConfiguration;

class PayPalTestConfiguration extends TestingProviderConfiguration {

	public static function get () {
		return self::createForProviderWithOverrideFile(
			'paypal',
			__DIR__ . '/config_test.yaml'
		);
	}

}
