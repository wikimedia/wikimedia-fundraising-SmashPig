<?php

namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\Tests\TestingProviderConfiguration;

class AmazonTestConfiguration extends TestingProviderConfiguration {

	public static function instance() {
		return self::createForProviderWithOverrideFile(
			'amazon',
			__DIR__ . '/config_test.yaml'
		);
	}
}
