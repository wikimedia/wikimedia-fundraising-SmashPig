<?php
namespace SmashPig\Core;

/**
 * Cascading configuration using YAML files
 */
class GlobalConfiguration extends Configuration {

	const DIR_VAR = 'SMASHPIG_GLOBAL_CONFDIR';
	const DEFAULT_BASEDIR = '/etc/smashpig';

	/**
	 * Creates a GlobalConfiguration object
	 *
	 * @return GlobalConfiguration
	 */
	public static function create() {
		$config = new static();
		$config->loadDefaultConfig();

		return $config;
	}

	/**
	 * Creates a configuration object, overriding values from files.
	 *
	 * @param array|string $overridePath  Extra configuration path(s) to search
	 *
	 * @return GlobalConfiguration or subclass
	 */
	public static function createWithOverrideFile( $overridePath ) {
		$config = new static();

		$searchPath = array_merge(
			( array ) $overridePath,
			$config->getDefaultSearchPath()
		);
		$config->loadConfigFromPaths( $searchPath );
		return $config;
	}

	protected function getDefaultSearchPath() {
		$searchPath = array();

		$baseDir = self::getBaseDirFromEnv( self::DIR_VAR, self::DEFAULT_BASEDIR );
		if ( isset( $_SERVER['HOME'] ) ) {
			// FIXME: But I don't understand why this key is missing during testing.
			$searchPath[] =  "{$_SERVER['HOME']}/.smashpig/main.yaml";
		}
		$searchPath[] = "$baseDir/main.yaml";
		$searchPath[] = __DIR__ . '/../config/main.yaml';
		return $searchPath;
	}

	protected function getDefaultOptions() {
		return array();
	}
}
