<?php

namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class BaseAdyenTestCase extends BaseSmashPigUnitTestCase {

	/**
	 * @var ProviderConfiguration
	 */
	public $config;

	public function setUp() {
		parent::setUp();
		$ctx = Context::get();
		$this->config = AdyenTestConfiguration::createWithSuccessfulApi(
			$ctx->getGlobalConfiguration()
		);
		$ctx->setProviderConfiguration( $this->config );
	}
}
