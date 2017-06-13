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
		$searchPath = array();
		// FIXME: this goes away along with all the 'view' stuff
		if ( $this->viewName !== 'default' ) {
			$searchPath[] = __DIR__ . "/../config/{$this->viewName}/main.yaml";
		}
		$searchPath[] = __DIR__ . "/../config/main.yaml";
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
