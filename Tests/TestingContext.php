<?php

namespace SmashPig\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\GlobalConfiguration;

class TestingContext extends Context {
	public $providerConfigurationOverride;

	public static function init(
		GlobalConfiguration $config, $providerConfig = null
	) {
		// Override the existing context
		Context::$instance = new TestingContext();
		if ( !$providerConfig ) {
			$providerConfig = TestingProviderConfiguration::createDefault();
		}
		Context::$instance->setProviderConfiguration( $providerConfig );
		Context::$instance->setGlobalConfiguration( $config );
	}

	public function getProviderConfiguration() {
		if ( $this->providerConfigurationOverride ) {
			return $this->providerConfigurationOverride;
		}
		return parent::getProviderConfiguration();
	}
}