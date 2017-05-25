<?php

namespace SmashPig\Tests;

use ReflectionClass;
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

	public static function loadConfigWithFileOverrides( $paths ) {
		$config = static::createForViewWithOverrideFile( 'default', $paths );
		return $config;
	}

	public static function loadConfigWithLiteralOverrides( $data ) {
		$config = static::createForView( 'default' );
		$config->override( $data );
		return $config;
	}
}
