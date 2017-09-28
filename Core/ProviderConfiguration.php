<?php

namespace SmashPig\Core;

class ProviderConfiguration extends Configuration {

	const NO_PROVIDER = 'no_provider';
	protected $provider;
	protected $machinewideBaseDirectory;

	protected function __construct( GlobalConfiguration $globalConfig ) {
		$baseDir = $globalConfig->val( 'provider-configuration-directory' );
		$this->machinewideBaseDirectory = $baseDir;
	}

	/**
	 * @param string $provider identifier of payment provider
	 * @param GlobalConfiguration $globalConfig
	 * @return static
	 */
	public static function createForProvider(
		$provider,
		GlobalConfiguration $globalConfig
	) {
		// FIXME: transitional code, remove when everything points to new names
		$provider = self::remapProvider( $provider );

		$config = new static( $globalConfig );
		$config->provider = $provider;
		$config->loadDefaultConfig();

		return $config;
	}

	/**
	 * @param string $provider
	 * @param string|array $overridePath
	 * @param GlobalConfiguration $globalConfig
	 * @return static
	 */
	public static function createForProviderWithOverrideFile(
		$provider,
		$overridePath,
		GlobalConfiguration $globalConfig
	) {
		// FIXME: transitional code, remove when everything points to new names
		$provider = self::remapProvider( $provider );

		$config = new static( $globalConfig );
		$config->provider = $provider;
		$searchPath = array_merge(
			(array)$overridePath,
			$config->getDefaultSearchPath()
		);
		$config->loadConfigFromPaths( $searchPath );
		return $config;
	}

	protected static function remapProvider( $provider ) {
		$remap = [
			'astropay' => 'dlocal',
			'globalcollect' => 'ingenico'
		];
		if ( array_key_exists( $provider, $remap ) ) {
			return $remap[$provider];
		}
		return $provider;
	}

	/**
	 * Create a ProviderConfiguration with just the defaults,
	 * to be used in processes that are not specific to one provider.
	 * We need this for logging in the pending queue consumer, for example.
	 * @param GlobalConfiguration $globalConfig
	 * @return static
	 */
	public static function createDefault( GlobalConfiguration $globalConfig ) {
		$config = new static( $globalConfig );
		$config->provider = self::NO_PROVIDER;
		$config->loadDefaultConfig();

		return $config;
	}

	protected function getDefaultSearchPath() {
		if ( $this->provider !== self::NO_PROVIDER ) {
			if ( isset( $_SERVER['HOME'] ) ) {
				$searchPath[] = "{$_SERVER['HOME']}/.smashpig/{$this->provider}/main.yaml";
			}
			$searchPath[] = "{$this->machinewideBaseDirectory}/{$this->provider}/main.yaml";
			$searchPath[] = __DIR__ . "/../config/{$this->provider}/main.yaml";
		}
		if ( isset( $_SERVER['HOME'] ) ) {
			$searchPath[] = "{$_SERVER['HOME']}/.smashpig/provider-defaults.yaml";
		}
		$searchPath[] = "{$this->machinewideBaseDirectory}/provider-defaults.yaml";
		$searchPath[] = __DIR__ . '/../config/provider-defaults.yaml';
		return $searchPath;
	}

	public function getProviderName() {
		return $this->provider;
	}
}
