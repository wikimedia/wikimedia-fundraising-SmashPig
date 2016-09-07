<?php
namespace SmashPig\Core;

use Symfony\Component\Yaml\Parser;

/**
 * Cascading configuration using YAML files
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
	public static function getDefaultConfig() {
		return Configuration::$defaultObj;
	}

	/**
	 * Set the default configuration object that will be returned by get_default_config()
	 *
	 * @param Configuration $obj
	 */
	protected static function setDefaultConfig( Configuration $obj ) {
		Configuration::$defaultObj = $obj;
	}

	/**
	 * Creates a configuration object for a specific configuration node.
	 *
	 * TODO: deprecate use of the direct constructor, in favor of
	 * loadDefaultConfig on your project's subclass of Configuration.

	 * @param string $view          Configuration view to load
	 * @param array|string|null $overridePath  Extra configuration path(s) to search
	 */
	public function __construct( $view = 'default', $overridePath = null ) {
		// FIXME: There's still something fishy about view.  Can we replace
		// this with a search path of higher-priority files?
		$this->viewName = $view;
		Configuration::setDefaultConfig( $this );

		if ( !$overridePath ) {
			$this->loadDefaultConfig();
		} else {
			$this->loadConfigFromPaths( array_merge(
				( array ) $overridePath,
				$this->getDefaultSearchPath()
			) );
		}
	}

	public function loadDefaultConfig() {
		$this->loadConfigFromPaths( $this->getDefaultSearchPath() );
	}

	public function getDefaultSearchPath() {
		$searchPath = array();
		if ( isset( $_SERVER['HOME'] ) ) {
			// FIXME: But I don't understand why this key is missing during testing.
			$searchPath[] =  "{$_SERVER['HOME']}/.fundraising/SmashPig.yaml";
		}
		$searchPath[] = '/etc/fundraising/SmashPig.d/*.yaml';
		$searchPath[] = '/etc/fundraising/SmashPig.yaml';
		$searchPath[] = __DIR__ . "/../SmashPig.yaml";
		return $searchPath;
	}

	/**
	 * Load a search path consisting of single files or globs
	 *
	 * Settings from files earlier in the list take precedence.  The funky
	 * "view" override happens here in a second step, with view data from all
	 * source files taking precedence over default data from all files.
	 *
	 * @param array $searchPath
	 */
	public function loadConfigFromPaths( $searchPath ) {
		$paths = $this->expandSearchPathToActual( $searchPath );

		if ( $this->loadConfigFromCache( $paths ) ) {
			// Config file loaded, nothing else to do.
			return;
		}

		// Reset to empty set.
		$this->options = array();

		// Attempt to load the configuration files from disk
		$configs = array();
		$yamlParser = new Parser();
		foreach ( $paths as $path ) {
			$config = $yamlParser->parse( file_get_contents( $path ) );
			if ( !is_array( $config ) ) {
				throw new \RuntimeException( "Bad config file format: '$path'" );
			}
			$configs[] = $config;
		}

		// Pull in all `default` sections first.
		// FIXME: The reverse thing is silly, but it's much simpler to merge
		// the sources up front than keep them distinct and search through them
		// at runtime for the first matching key.
		foreach ( array_reverse( $configs ) as $config ) {
			if ( isset( $config['default'] ) ) {
				$this->override( $config['default'] );
			}
		}

		// Now, go through in the same order and let all $view sections override
		// defaults.
		if ( $this->viewName !== 'default' ) {
			foreach ( array_reverse( $configs ) as $config ) {
				if ( isset( $config[$this->viewName] ) ) {
					$this->override( $config[$this->viewName] );
				}
			}
		}

		// Store the configuration to cache, if possible
		$this->saveConfigToCache( $paths );
	}

	/**
	 * Flatten and unglob the search path.
	 *
	 * @param array $searchPath File paths or globs
	 * @return array Actual files discovered in the path.
	 */
	protected function expandSearchPathToActual( $searchPath ) {
		$paths = array_reduce( $searchPath, function ( $carry, $pattern ) {
			$matchingPaths = glob( $pattern );
			if ( $matchingPaths === false ) {
				throw new \RuntimeException( "Illegal glob while matching {$pattern}" );
			}
			return array_merge( $carry, $matchingPaths );
		}, array() );

		return $paths;
	}

	/**
	 * Override configuration with an array of data
	 *
	 * Note that these overrides take precedence over every configuration file,
	 * so any usage outside of this class or tests will be subverting the
	 * expected cascading priority.
	 *
	 * @param $data array
	 */
	public function override( $data ) {
		static::treeMerge( $this->options, $data );
	}

	/**
	 * Loads a configuration file from the cache if it is still valid (ie: source files have not
	 * changed)
	 *
	 * TODO: Generalize to any caching backend.
	 *
	 * @param array  $paths    Paths we read from
	 *
	 * @return bool True if the config was loaded successfully.
	 */
	protected function loadConfigFromCache( $paths ) {
		if ( !$this->hasApc() ) {
			return false;
		}

		$fileModifiedTimes = array_map( function ( $path ) {
			$fileModifiedTimes[] = filemtime( $path );
		}, $paths );

		// TODO: Cache the config for each installation's paths.
		$cacheObj = apc_fetch( "smashpig-settings-{$this->viewName}", $success );

		if ( !$success
			|| empty( $cacheObj['configTimes'] )
			|| empty( $cacheObj['paths'] )
		) {
			return false;
		}

		if ( implode( ':', $paths ) === $cacheObj['paths']
			&& implode( ':', $fileModifiedTimes ) === $cacheObj['configTimes']
		) {
			// The cached values are valid
			// TODO: log safely.
			$this->options = $cacheObj['values'];
			return true;
		}

		return false;
	}

	protected function hasApc() {
		static $useApc = null;
		if ( $useApc === null ) {
			$useApc = extension_loaded( 'apc' );
		}
		return $useApc;
	}

	/**
	 * Saves the loaded configuration to the cache.
	 *
	 * @param array $paths Paths we read from
	 * @param string $node Node name that we're saving to cache
	 */
	protected function saveConfigToCache( $paths ) {
		if ( !$this->hasApc() ) {
			return;
		}

		$fileModifiedTimes = array_map( function ( $path ) {
			$fileModifiedTimes[] = filemtime( $path );
		}, $paths );

		apc_store(
			"smashpig-settings-{$this->viewName}",
			array(
				 'paths' => implode( ':', $paths ),
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
	 * @param bool $returnRef     If true will return a reference to the configuration node. This will
	 *                            mean that any modifications to the node will be stored in RAM for the
	 *                            duration of the session.
	 * @return mixed
	 * @throws ConfigurationKeyException
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
	 * @param bool $persistent   If true the object is saved for future calls.
	 * @return mixed|object
	 * @throws ConfigurationKeyException
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
	 * @param string $node Node path; ie: logging/logstreams/syslog/class
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
	 * @throws SmashPigException
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
	public $key;

	public function __construct( $message = null, $key = null, $previous = null ) {
		parent::__construct( $message, 0, $previous );
		$this->key = $key;
	}
}
