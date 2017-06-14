<?php
namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\Core\GlobalConfiguration;
use SmashPig\Tests\TestingProviderConfiguration;

class PayPalTestConfiguration extends TestingProviderConfiguration {

	public static function get( GlobalConfiguration $globalConfig ) {
		return self::createForProviderWithOverrideFile(
			'paypal',
			__DIR__ . '/config_test.yaml',
			$globalConfig
		);
	}

}
