<?php
namespace SmashPig\Core;

use Symfony\Component\Yaml\Parser;

/**
 * Cascading configuration using YAML files
 */
class Configuration {

	/** @var array K/V array of configuration options for the initialized node */
	protected $options = array();

	/** @var array keyed on class name that stores persistent objects */
	protected $objects = array();

	/**
	 * @var string Name of the view that generated this configuration object
	 *
	 * FIXME: There's still something fishy about view.
	 */
	protected $viewName = 'default';

	/**
	 * Creates a configuration object for a specific configuration node.
	 *
	 * @param string $view Configuration view to load
	 * FIXME: No reason to provide a default.
	 *
	 * @return Configuration or subclass
	 */
	public static function createForView( $view = 'default' ) {
		$config = new static();
		$config->viewName = $view;
		$config->loadDefaultConfig();

		return $config;
	}

	/**
	 * Creates a configuration object for a specific configuration node.
	 *
	 * FIXME: Don't provide defaults once usages are cleaned up.
	 *
	 * @param string $view Configuration view to load
	 * @param array|string|null $overridePath  Extra configuration path(s) to search
	 *
	 * @return Configuration or subclass
	 */
	public static function createForViewWithOverrideFile( $view = 'default', $overridePath = null ) {
		$config = new static();
		$config->viewName = $view;

		if ( !$overridePath ) {
			$config->loadDefaultConfig();
		} else {
			$searchPath = array_merge(
				( array ) $overridePath,
				$config->getDefaultSearchPath()
			);
			$config->loadConfigFromPaths( $searchPath );
		}
		return $config;
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
	 * For testing: provide a specific instance of an object to fulfil requests
	 * for a specific node. Helpful when using test library mocks that you can't
	 * declaratively configure with constructor parameters.
	 *
	 * @param $node string
	 * @param $object object
	 */
	public function overrideObjectInstance( $node, $object ) {
		$this->objects[$node] = $object;
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
				// TODO: Don't offer a return-by-reference.
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
	 * instantiated with any arguments as given in the subkey 'constructor-parameters'.
	 *
	 * When arguments are given it should be a simple list with arguments in the expected order.
	 *
	 * Example:
	 * data_source:
	 *      class: DataSourceClass
	 *      constructor-parameters:
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
	public function object( $node, $persistent = true ) {
		// First look and see if we already have a $persistent object.
		if ( array_key_exists( $node, $this->objects ) ) {
			return $this->objects[$node];
		}

		$className = $this->val( $node . '/class' );

		// Optional keys
		$arguments = array();
		// It would be nice to be able to provide other objects defined
		// in config as arguments. We might have had that pre-simplification
		// with nodes that referred to other nodes.
		if ( $this->nodeExists( $node . '/constructor-parameters' ) ) {
			$arguments = $this->val( $node . '/constructor-parameters' );
		}

		$reflectedObj = new \ReflectionClass( $className );
		$obj = $reflectedObj->newInstanceArgs( $arguments );

		if ( $persistent ) {
			$this->objects[$node] = $obj;
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
	protected static function treeMerge( &$base, $graft, $myRoot = '' ) {
		foreach ( $graft as $graftNodeName => $graftNodeValue ) {
			$node = ($myRoot ? "{$myRoot}/{$graftNodeName}" : $graftNodeName);

			if ( array_key_exists( $graftNodeName, $base ) ) {
				$baseNodeRef = &$base[$graftNodeName];
				// Nodes that are present in the base and in the graft

				if (
					is_array( $graftNodeValue ) &&
					self::isMergable( $baseNodeRef, $graftNodeValue )
				) {
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
