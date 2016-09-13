<?php

namespace SmashPig\Tests;

use SmashPig\Core\Configuration;

/**
 * Do trixy things with Configuration
 */
class TestingConfiguration extends Configuration {
	/**
	 * Set default search path to skip actual installed configuration like /etc
	 *
	 * @implements Configuration::getDefaultSearchPath
	 */
	public function getDefaultSearchPath() {
		$searchPath = array(
			__DIR__ . "/../SmashPig.yaml",
		);
		return $searchPath;
	}

	public static function installTestConfiguration( $pathOverrides = array() ) {
		// Late static binding so that a subclass creates one of itself
		$singleton = static::createForViewWithOverrideFile( 'default', $pathOverrides );
		Configuration::setDefaultConfig( $singleton );
		return $singleton;
	}

	public static function loadConfigWithFileOverrides( $paths ) {
		$config = static::installTestConfiguration( $paths );
		return $config;
	}

	public static function loadConfigWithLiteralOverrides( $data ) {
		$config = static::installTestConfiguration();
		$config->override( $data );
		return $config;
	}
}
