<?php namespace SmashPig\Core;

/**
 * Represents actions onto a configuration view.
 *
 * '@' is a redirection operator. If any key ends with '@' the value is assumed to be a new path
 * in the configuration file. Note that due to how the merge algorithm and then search algorithms
 * work, a redirected configuration option will never take precedence over a explicitly defined
 * option.
 */
class Configuration {

	/** @var Configuration the default configuration object returned when get_default_config is called */
	protected static $defaultObj;

	/** @var array K/V array of configuration options for the initialized node */
	protected $options = array();

	/** @var array keyed on class name that stores persistent objects */
	protected $objects = array();

	/** @var string Name of the view that generated this configuration object */
	protected $viewName = 'default';

	/**
	 * Obtains the current default configuration object. Typically the object created by index.php
	 *
	 * @return Configuration
	 */
	public static function &getDefaultConfig() {
		return Configuration::$defaultObj;
	}

	/**
	 * Set the default configuration object that will be returned by get_default_config()
	 *
	 * @param Configuration $obj
	 */
	protected static function setDefaultConfig( Configuration &$obj ) {
		Configuration::$defaultObj = $obj;
	}

	/**
	 * Creates a configuration object for a specific configuration node.
	 *
	 * @param string $defaultFile   Path to config_defaults.php
	 * @param string $overrideFile  Path to config.php
	 * @param string $view          Configuration view to load
	 * @param bool   $setDefault    If this configuration should be the object returned from get_default_config()
	 */
	public function __construct( $defaultFile, $overrideFile = null, $view = 'default', $setDefault = false ) {
		// Check to make sure APC is installed :) Yay caching for expensive operations!
		$useApc = false;
		if ( extension_loaded( 'apc' ) ) {
			$useApc = true;
		}

		if ( !file_exists( $defaultFile ) ) {
			throw new ConfigurationException( "Default configuration file not found at {$defaultFile}" );
		}
		if ( $overrideFile && !file_exists( $overrideFile ) ) {
			throw new ConfigurationException( "Site configuration file given but not found at {$overrideFile}" );
		}

		if ( $useApc && $this->loadConfigFromCache( $defaultFile, $overrideFile, $view ) ) {
			// Config file loaded, nothing else to do
		} else {
			// Attempt to load the configuration files from disk
			global $config_defaults, $config;

			require_once( $defaultFile );
			if ( $overrideFile && file_exists( $overrideFile ) ) {
				include_once( $overrideFile );
			}

			// Now that we have the files we must merge four things in the following order:
			// default: default, override: default, default: $view, override: $view
			$this->options = $config_defaults[ 'default' ];
			if ( !empty( $config ) && array_key_exists( 'default', $config ) ) {
				static::treeMerge( $this->options, $config[ 'default' ] );
			}
			if ( array_key_exists( $view, $config_defaults ) ) {
				static::treeMerge( $this->options, $config_defaults[ $view ] );
			}
			if ( !empty( $config ) && array_key_exists( $view, $config ) ) {
				static::treeMerge( $this->options, $config[ $view ] );
			}

			// Store the configuration to cache if possible
			if ( $useApc ) {
				$this->saveConfigToCache( $defaultFile, $overrideFile, $view );
			}
		}

		$this->viewName = $view;
		if ( $setDefault ) {
			Configuration::setDefaultConfig( $this );
		}
	}

	/**
	 * Loads a configuration file from the cache if it is still valid (ie: source files have not
	 * changed)
	 *
	 * @param string $defaultFile   Path to config_defaults.php
	 * @param string $overrideFile  Path to config.php
	 * @param string $view          The configuration view to load
	 *
	 * @return bool True if the config was loaded successfully.
	 */
	protected function loadConfigFromCache( $defaultFile, $overrideFile, $view ) {
		$defaultFileTime = filemtime( $defaultFile );

		if ( $overrideFile ) {
			$overrideFileTime = filemtime( $overrideFile );
		} else {
			$overrideFileTime = 0;
		}

		$cacheObj = apc_fetch( "smashpig-settings-{$view}-time", $success );

		if ( $success ) {
			$defaultCacheTime = $cacheObj[ 'default-time' ];
			$overrideCacheTime = $cacheObj[ 'override-time' ];

			if ( ( $defaultFileTime == $defaultCacheTime ) && ( $overrideFileTime == $overrideCacheTime ) ) {
				// The cached values are valid
				$this->options = $cacheObj[ 'values' ];
				return true;
			}
		}

		return false;
	}

	/**
	 * Saves the loaded configuration to the cache.
	 *
	 * @param $defaultFile  Path to config_defaults.php
	 * @param $overrideFile Path to config.php
	 * @param $node         Node name that we're saving to cache
	 */
	protected function saveConfigToCache( $defaultFile, $overrideFile, $node ) {
		$defaultFileTime = filemtime( $defaultFile );
		$overrideFileTime = filemtime( $overrideFile );

		apc_store(
			"smashpig-settings-{$node}",
			array(
				 'default-time'  => $defaultFileTime,
				 'override-time' => $overrideFileTime,
				 'values'        => $this->options,
			)
		);
	}

	/**
	 * Obtain a value from the configuration. If the key does not exist this will throw an
	 * exception.
	 *
	 * @param string $node        Parameter node to obtain. If this contains '/' it is assumed that the
	 *                            value is contained under additional keys.
	 * @param bool   $returnRef   If true will return a reference to the configuration node. This will
	 *                            mean that any modifications to the node will be stored in RAM for the
	 *                            duration of the session.
	 */
	public function &val( $node, $returnRef = false ) {
		$keys = explode( '/', $node );

		$croot = & $this->options;
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $croot ) ) {
				// Straight up path
				$croot = & $croot[ $key ];
			} elseif ( array_key_exists( $key . '@', $croot ) ) {
				// We've been redirected!
				$redirKey = $croot[ $key . '@' ];
				try {
					$croot = $this->val( $redirKey );
				} catch ( ConfigurationKeyException $ex ) {
					throw new ConfigurationKeyException(
						"Redirection key '{$redirKey}' does not exist while looking for key '{$node}'.",
						$key
					);
				}
			} else {
				throw new ConfigurationKeyException( "Configuration key '{$node}' does not exist.", $node );
			}
		}

		if ( $returnRef ) {
			return $croot;
		} else {
			// Dereference the variable
			$obj = $croot;
			return $obj;
		}
	}

	/**
	 * Creates an object from the configuration file. This works by looking up the configuration
	 * key name which will be an array with at least a subkey of 'class'. The class will then be
	 * instantiated with any arguments as given in the subkey 'inst-args'.
	 *
	 * NOTE: This will return a reference to the object!
	 *
	 * When arguments are given it should be a simple list with arguments in the expected order.
	 * Redirections are not allowed in class instantation arguments. If this is a required feature
	 * consider writing the class to take a configuration.
	 *
	 * Example:
	 * 'data_source' => array(
	 *      'class' => 'DataSourceClass',
	 *      'inst-args' => array(
	 *          'argument1',
	 *          'foo/bar/baz'
	 *      )
	 * )
	 *
	 * @param string $node       Parameter node to obtain. If this contains '/' it is assumed that the
	 *                           value is contained under additional keys.
	 * @param bool   $persistent If true the object is saved for future calls.
	 */
	public function &obj( $node, $persistent = true ) {
		// First look and see if we already have a $persistent object.
		if ( array_key_exists( $node, $this->objects ) ) {
			return $this->objects[ $node ];
		}

		try {
			$className = $this->val( $node . '/class' );
			$arguments = $this->val( $node . '/inst-args' );
		} catch ( ConfigurationKeyException $ex ) {
			throw new ConfigurationKeyException(
				"Could not instantiate class from key '{$node}'. Missing required key '{$ex->key}'",
				$node,
				$ex
			);
		}

		$reflectedObj = new \ReflectionClass( $className );
		$obj = $reflectedObj->newInstanceArgs( $arguments );

		if ( $persistent ) {
			$this->objects[ $node ] = $obj;
		}
		return $obj;
	}

	/**
	 * Determine if a given configuration node exists in the loaded configuration.
	 *
	 * @param $node Node path; ie: logging/logstreams/syslog/class
	 *
	 * @return bool True if the node exists
	 */
	public function nodeExists( $node ) {
		try {
			$this->val( $node, true );
			return true;
		} catch ( ConfigurationKeyException $ex ) {
			return false;
		}
	}

	/**
	 * @return string The name of the view used to generate this configuration object
	 */
	public function getViewName() {
		return $this->viewName;
	}

	/**
	 * Merge two arrays recursively. The $graft array will overwrite any value in the $base
	 * array where the $base array does not have an array at that node. If it does have an
	 * array the merge will continue recursively.
	 *
	 * @param array $base  The base array to merge into
	 * @param array $graft Values to merge into the $base
	 */
	private static function treeMerge( &$base, $graft ) {
		foreach ( $graft as $graftNodeName => $graftNodeValue ) {
			if ( array_key_exists( $graftNodeName, $base ) ) {
				// Nodes that are present in the base and in the graft
				if ( is_array( $base[ $graftNodeName ] ) ) {
					static::treeMerge( $base[ $graftNodeName ], $graftNodeValue );
				} else {
					$base[ $graftNodeName ] = $graftNodeValue;
				}
			} else {
				// Nodes that are only present in the graft
				$base[ $graftNodeName ] = $graftNodeValue;
			}
		}
	}
}

/**
 * Exception thrown on generic configuration error.
 */
class ConfigurationException extends SmashPigException {
}

/**
 * Exception thrown when a configuration key is not valid or has some other problem.
 */
class ConfigurationKeyException extends ConfigurationException {
	public function __construct( $message = null, $key = null, $previous = null ) {
		parent::__construct( $message, 0, $previous );
		$this->key = $key;
	}
}
