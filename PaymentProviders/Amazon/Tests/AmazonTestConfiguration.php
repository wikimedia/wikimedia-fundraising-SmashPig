<?php

namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\Core\Configuration;

class AmazonTestConfiguration extends Configuration{

	public static function instance() {
		return self::createForViewWithOverrideFile(
			'amazon',
			__DIR__ . '/config_test.yaml'
		);
	}
}
