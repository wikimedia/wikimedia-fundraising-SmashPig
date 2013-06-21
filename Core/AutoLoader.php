<?php namespace SmashPig\Core;
use SmashPig\Core\Logging\Logger;

/**
 * PSR-0 compliant autoloader
 * (https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md)
 */
class AutoLoader {

	/**
	 * @var AutoLoader Installed instance.
	 */
	protected static $instance = null;

	/**
	 * Installs the SmashPig AutoLoader into the class loader chain.
	 * Will also load the composer autoloader.
	 *
	 * @param string $defaultPath The path to the SmashPig library.
	 *
	 * @return bool True if install was successful. False if the AutoLoader was already installed.
	 */
	public static function installSmashPigAutoLoader( $defaultPath = null ) {
		if ( static::$instance ) {
			return false;
		} else {
			if ( $defaultPath === null ) {
				$defaultPath = AutoLoader::getInstallPath();
			}
			static::$instance = new AutoLoader( $defaultPath );

			return true;
		}
	}

	/**
	 * Returns the installed SmashPig autoloader object.
	 *
	 * @return AutoLoader instance
	 * @throws SmashPigException If there has been no autoloader installed.
	 */
	public static function getInstance() {
		if ( static::$instance ) {
			return static::$instance;
		} else {
			throw new SmashPigException( 'AutoLoader has not been installed. See AutoLoader::install_smashpig_autoloader().' );
		}
	}

	/** @var array Tree of namespaces; keys are namespace names or @ which
	 * indicates the namespace may be found at the location specified in value
	 */
	protected $namespaceDirs = array();

	protected function __construct( $defaultPath ) {
		$this->addNamespacePath( 'SmashPig', $defaultPath );

		spl_autoload_register( array( $this, 'autoload' ) );
	}

	public function addConfiguredNamespaces() {
		$config = Configuration::getDefaultConfig();

		// Load additional namespaces into the autoloader
		foreach ( $config->val( 'namespaces' ) as $namespace ) {
			\SmashPig\Core\Logging\Logger::debug( 'Loading additional namespace node.', $namespace );
			AutoLoader::getInstance()->addNamespacePath(
				$namespace[ 'namespace' ],
				$namespace[ 'disk-path' ]
			);
		}
	}

	public function addConfiguredIncludePaths() {
		$config = Configuration::getDefaultConfig();

		$path = get_include_path();

		// Load additional namespaces into the autoloader
		foreach ( $config->val( 'include-paths' ) as $path ) {
			$path .= PATH_SEPARATOR . $path;
		}

		Logger::debug( 'Setting include path to: ' . $path );
		set_include_path( $path );
	}

	/**
	 * Include
	 */
	public function addConfiguredIncludes() {
		$defaultPath = AutoLoader::getInstallPath();
		$config = Configuration::getDefaultConfig();

		foreach ( $config->val( 'include-files' ) as $file ) {
			if ( substr( $file, 0, 1 ) !== DIRECTORY_SEPARATOR ) {
				// Path relative so modify it
				$file = AutoLoader::makePath( $defaultPath, $file );
			}
			Logger::debug( "AutoLoader including file '$file'" );
			require_once( $file );
		}
	}

	/**
	 * Add a new location path for a namespace. Will throw an error if the namespace
	 * already has a location.
	 *
	 * @param string    $namespace  Fully qualified namespace name
	 * @param string    $path       Location on disk
	 *
	 * @throws SmashPigException if the namespace already exists
	 */
	public function addNamespacePath( $namespace, $path ) {
		$namespace = trim( $namespace, '\\' );
		$parts = explode( '\\', $namespace );

		$ref = & $this->namespaceDirs;
		foreach ( $parts as $part ) {
			if ( !array_key_exists( $part, $ref ) ) {
				$ref[ $part ] = array();
			}
			$ref = & $ref[ $part ];
		}

		if ( array_key_exists( '@', $ref ) ) {
			throw new SmashPigException( "Namespace $namespace already has a loader entry." );
		}

		$ref[ '@' ] = realpath( $path );
	}

	/**
	 * Called by the SPL autoloader to load a class. This function will do namespace/directory
	 * mapping as specified in PSR-0 unless the namespace prefix is registered via
	 *
	 * @see addNamespacePath.
	 *
	 * PSR-0 TLDR
	 *  - Classes reside in unique files named after the class
	 *  - Classes reside in a folder tree identical to the namespace tree
	 *  - '_' in class names are treated as additional subtree delimiters
	 *
	 * @param string     $fqcn     The fully qualified class name
	 *
	 * @return bool True if the class was loaded successfully
	 */
	public function autoload( $fqcn ) {
		$fqcn = trim( $fqcn, '\\' );

		if ( strpos( $fqcn, '\\' ) !== false ) {
			// Obtain all the relevant bits from the fqcn
			$classPathArray = preg_split( '/[\\\\_]/', $fqcn );
			$className = array_pop( $classPathArray );
			$classPath = implode( '\\', $classPathArray ) . '\\';

			// Now traverse the namespace tree finding the deepest redirect
			$treeref = & $this->namespaceDirs;
			$subspace = '';

			$nspath = null;
			$diskpath = null;

			foreach ( $classPathArray as $part ) {
				if ( array_key_exists( $part, $treeref ) ) {
					$treeref = & $treeref[ $part ];
					$subspace .= $part . '\\';
					if ( array_key_exists( '@', $treeref ) ) {
						$nspath = $subspace;
						$diskpath = $treeref[ '@' ];
					}
				} else {
					break;
				}
			}

			// Replace the redirect
			if ( $nspath !== null ) {
				$diskpath .= DIRECTORY_SEPARATOR;
				$classPath = str_replace( $nspath, $diskpath, $classPath );
			} else {
				// We don't have an entry in our tree for this namespace. No point in
				// attempting to continue. Let another loader attempt to find it.
				return false;
			}

			// Attempt to load
			$file = $this->makePath( $classPath, "{$className}.php" );

			// Load if exists and check success
			if ( file_exists( $file ) ) {
				require( $file );
				if ( class_exists( '\\' . $fqcn, false ) ) {
					return true;
				}
			}

			// If we're here, the load failed
			return false;

		} else {
			// This class has no clue how to load non namespaced items
			return false;
		}
	}

	/**
	 * Make a filesystem path given a collection of parts which may be paths in and
	 * of themselves. E.g. makePath( '/foo/bar/', 'bash', 'config.php' ) will turn
	 * into '/foo/bar/bash/config.php'. The direction of the slashes is irrelevant,
	 * both will be turned into the system default.
	 *
	 * @param string $parts Arbitrary number of path elements
	 *
	 * @return string Path; will never have a following DIRECTORY_SEPARATOR
	 */
	public static function makePath( $parts /* ... */ ) {
		$params = func_get_args();

		// Convert forward and backward slashes into system default
		// At the same time, ensure that no part ends with DIR_SEP
		array_walk(
			$params,
			function ( &$part ) {
				$part = str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, (string)$part );
				if ( substr( $part, -1 ) === DIRECTORY_SEPARATOR ) {
					$part = substr( $part, 0, -1 );
				}
			}
		);

		return implode( DIRECTORY_SEPARATOR, $params );
	}

	/**
	 * Obtains the SmashPig core install path.
	 *
	 * @return string SmashPig core install path
	 */
	public static function getInstallPath() {
		return realpath( AutoLoader::makePath( __DIR__, '..' ) );
	}
}
