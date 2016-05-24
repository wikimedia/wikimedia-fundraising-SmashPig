<?php
namespace SmashPig\Core;

use Symfony\Component\Yaml\Parser;

/**
 * Represents actions onto a configuration view.
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
	 * Obtains the current default configuration object. You should probably be
	 * using a context call instead of this. See Context->getConfiguration()
	 *
	 * Typically the object created by index.php
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
	 * @param string $view          Configuration view to load
	 * @param string $overridePath  Extra configuration path to search
	 */
	public function __construct( $view = 'default', $overridePath = null ) {
		// Check to make sure APC is installed :) Yay caching for expensive operations!
		$useApc = false;
		if ( extension_loaded( 'apc' ) ) {
			$useApc = true;
		}

		$searchPath = array(
			__DIR__ . '/../SmashPig.yaml',
			'/etc/fundraising/SmashPig.yaml',
			$_SERVER['HOME'] . '/.fundraising/SmashPig.yaml',
		);
		if ( $overridePath ) {
			$searchPath[] = $overridePath;
		}

		if ( $useApc && $this->loadConfigFromCache( $searchPath, $view ) ) {
			// Config file loaded, nothing else to do
		} else {
			// Attempt to load the configuration files from disk
			$configs = array();
			$yaml = new Parser();
			foreach ( $searchPath as $path ) {
				if ( file_exists( $path ) ) {
					$config = $yaml->parse( file_get_contents( $path ) );
					if ( !is_array( $config ) ) {
						throw new \RuntimeException( "Bad config file format: '$path'" );
					}
					$configs[] = $config;
				}
			}

			// Pull in all default sections first, using the following precedence:
			// 1. ~/.fundraising/SmashPig.yaml
			// 2. /etc/fundraising/SmashPig.yaml
			// 3. <source dir>/SmashPig.yaml
			$this->options = array();
			foreach ( $configs as $config ) {
				if ( isset( $config['default'] ) ) {
					static::treeMerge( $this->options, $config['default'] );
				}
			}

			// Now, go through in the same order and let all $view sections override
			// defaults.
			if ( $view && $view !== 'default' ) {
				foreach ( $configs as $config ) {
					if ( isset( $config[$view] ) ) {
						static::treeMerge( $this->options, $config[$view] );
					}
				}
			}

			// Store the configuration to cache if possible
			if ( $useApc ) {
				$this->saveConfigToCache( $searchPath, $view );
			}
		}

		$this->viewName = $view;
		Configuration::setDefaultConfig( $this );
	}

	/**
	 * Loads a configuration file from the cache if it is still valid (ie: source files have not
	 * changed)
	 *
	 * @param array  $searchPath    Paths we read from
	 * @param string $view          The configuration view to load
	 *
	 * @return bool True if the config was loaded successfully.
	 */
	protected function loadConfigFromCache( $searchPath, $view ) {
		$fileModifiedTimes = array();
		foreach ( $searchPath as $path ) {
			if ( file_exists( $path ) ) {
				$fileModifiedTimes[] = filemtime( $path );
			} else {
				$fileModifiedTimes[] = 0;
			}
		}

		// TODO: Cache the config for each installation's searchPath.
		$cacheObj = apc_fetch( "smashpig-settings-{$view}", $success );

		if ( !$success
			|| empty( $cacheObj['configTimes'] )
			|| empty( $cacheObj['searchPath'] )
		) {
			return false;
		}

		if ( implode( ':', $searchPath ) === $cacheObj['searchPath']
			&& implode( ':', $fileModifiedTimes ) === $cacheObj['configTimes']
		) {
			// The cached values are valid
			// TODO: log safely.
			$this->options = $cacheObj['values'];
			return true;
		}

		return false;
	}

	/**
	 * Saves the loaded configuration to the cache.
	 *
	 * @param array $searchPath Paths we read from
	 * @param string $node Node name that we're saving to cache
	 */
	protected function saveConfigToCache( $searchPath, $node ) {
		$fileModifiedTimes = array();
		foreach ( $searchPath as $path ) {
			if ( file_exists( $path ) ) {
				$fileModifiedTimes[] = filemtime( $path );
			} else {
				$fileModifiedTimes[] = 0;
			}
		}

		apc_store(
			"smashpig-settings-{$node}",
			array(
				 'searchPath' => implode( ':', $searchPath ),
				 'configTimes' => implode( ':', $fileModifiedTimes ),
				 'values' => $this->options,
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
		/*
		 * Magic "/" returns the entire configuration tree.
		 *
		 * Question: Is this "/" trick intuitive enough to absolve it of being
		 * a magic number?
		 *
		 * Note: Never log this tree insecurely, it will contain processor
		 * credentials and other sensitive information.
		 */
		if ( $node === '/' ) {
			if ( $returnRef ) {
				$options = &$this->options;
			} else {
				$options = $this->options;
			}
			return $options;
		}

		$keys = explode( '/', $node );

		$croot = & $this->options;
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $croot ) ) {
				$croot = & $croot[ $key ];
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
	 *
	 * Example:
	 * data_source:
	 *      class: DataSourceClass
	 *      inst-args:
	 *          - argument1
	 *          - foo/bar/baz
	 *
	 * @param string $node       Parameter node to obtain. If this contains '/'
	 *                           it is assumed that the value is contained
	 *                           under additional keys.
	 * @param bool   $persistent If true the object is saved for future calls.
	 */
	public function &object( $node, $persistent = true ) {
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
	 *
	 * @param string $myRoot Internal recursion state: parent node path so far,
	 * or empty string to begin.
	 */
	private static function treeMerge( &$base, $graft, $myRoot = '' ) {
		foreach ( $graft as $graftNodeName => $graftNodeValue ) {
			$node = ($myRoot ? "{$myRoot}/{$graftNodeName}" : $graftNodeName);

			if ( array_key_exists( $graftNodeName, $base ) ) {
				$baseNodeRef = &$base[$graftNodeName];
				// Nodes that are present in the base and in the graft

				if (!self::isMergable($baseNodeRef, $graftNodeValue)) {
					// Stop if types don't match.
					throw new SmashPigException(
						"Dissimilar types cannot be merged at configuration node {$node}." );
				}

				if ( is_array( $graftNodeValue ) ) {
					// Recursively merge arrays.
					static::treeMerge( $baseNodeRef, $graftNodeValue, $node );
				} else {
					$baseNodeRef = $graftNodeValue;
				}
			} else {
				// Nodes that are only present in the graft
				$base[$graftNodeName] = $graftNodeValue;
			}
		}
	}

	/**
	 * Check that valueB can be merged on top of valueA.
	 */
	static protected function isMergable($valueA, $valueB) {
		if (gettype($valueA) !== gettype($valueB)) {
			// Plain old different type
			return false;
		}

		// Test for numeric vs map "array"--gotta love it.
		if (is_array($valueA) && is_array($valueB)

			// If either is empty, don't sweat it.
			&& $valueA && $valueB

			// If either has element [0], so must the other.
			&& (array_key_exists(0, $valueA)
				xor array_key_exists(0, $valueB))
		) {
			return false;
		}

		// Feeling lucky.
		return true;
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
