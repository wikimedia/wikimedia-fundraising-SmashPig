<?php

namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\Core\Configuration;

class AmazonTestConfiguration extends Configuration{

	public function __construct() {
		return parent::__construct(
			'amazon',
			__DIR__ . '/config_test.yaml'
		);
	}
}
