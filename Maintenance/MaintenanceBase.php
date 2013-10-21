<?php namespace SmashPig\Maintenance;
/**
 * Base include file for all PHP maintenance scripts.
 *
 * Maintenance scripts shall inherit from MaintenanceBase and should, if command
 * line executable, require this file at the top of the script, require(RUN_MAINTENANCE_IF_MAIN)
 * at the bottom of the script, and set $maintClass to the class name of the script.
 */

use SmashPig\Core\AutoLoader;
use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\LogStreams\ConsoleLogStream;
use SmashPig\Core\SmashPigException;

/** Help scripts find the postscript required for maintenance scripts */
define( 'RUN_MAINTENANCE_IF_MAIN', __DIR__ . '/doMaintenance.php' );

if ( !defined( "SMASHPIG_ENTRY_POINT" ) ) {
	global $argv;
	define( "SMASHPIG_ENTRY_POINT", $argv[0] );

	// Start the Autoloader
	$root = __DIR__ . '/../';
	require_once( $root . 'Core/AutoLoader.php' );
	AutoLoader::installSmashPigAutoLoader( $root );

	/** @var MaintenanceBase $maintClass Set this to the name of the class to execute */
	$maintClass = false;
}

/**
 * Abstract maintenance class for quickly writing maintenance scripts.
 * Only the execute() method need be defined for a maintenance script to run.
 */
abstract class MaintenanceBase {
	/** Const for getStdin() **/
	const STDIN_ALL = 'all';

	/** @var array Desired parameters. Keys are long names, values are arrays with keys 'desc', 'default', and 'alias' */
	protected $desiredOptions = array();

	/** @var array Map aliased parameter names to long ones, e.g. -h -> --help */
	protected $aliasParamsMap = array();

	/** @var array Arguments expected on the command line */
	protected $expectedArguments = array();

	/** @var array List of options that were actually passed */
	protected $options = array();

	/** @var array List of arguments that were actually passed */
	protected $args = array();

	/** @var string Name of the script that is actually executing */
	protected $scriptName = '';

	/** @var string Script description; children should change this to something useful */
	protected $description = '';

	/** @var bool True if the command line arguments have been processed */
	protected $inputLoaded = false;

	/* === Execution Boilerplate === */

	/**
	 * Default constructor. Does not really do anything except adds the default parameters.
	 * The real magic happens in setup().
	 */
	public function __construct() {
		$this->addDefaultParams();
	}

	/**
	 * Determine if this script should be executed as a maintenance script or
	 * merely loaded as a class. This works by looking at the script name as
	 * passed through argv and comparing it to the contents of SMASHPIG_ENTRY_POINT.
	 *
	 * @return bool True if the script execute() should automatically be called.
	 */
	public static function shouldExecute() {
		global $argv;
		if ( $argv[0] === SMASHPIG_ENTRY_POINT ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Do some sanity checking and framework setup
	 */
	public function setup() {
		global $maintClass;

		// Abort if called from a web server
		if ( isset( $_SERVER ) && isset( $_SERVER['REQUEST_METHOD'] ) ) {
			$this->error( 'This script must be run from the command line', true );
		}

		if ( version_compare( phpversion(), '5.2.4' ) >= 0 ) {
			// Send PHP warnings and errors to stderr instead of stdout.
			// This aids in diagnosing problems, while keeping messages
			// out of redirected output.
			if ( ini_get( 'display_errors' ) ) {
				ini_set( 'display_errors', 'stderr' );
			}

			// Don't touch the setting on earlier versions of PHP,
			// as setting it would disable output if you'd wanted it.

			// Note that exceptions are also sent to stderr when
			// command-line mode is on, regardless of PHP version.
		}

		// Set max execution time to 0 (no limit). PHP.net says that
		// "When running PHP from the command line the default setting is 0."
		// But sometimes this doesn't seem to be the case.
		ini_set( 'max_execution_time', 0 );

		$this->loadParamsAndArgs();
		$this->helpIfRequested();
		$this->adjustMemoryLimit();

		// --- Initialize core services ---
		$configFile = $this->getOption( 'config-file' );
		$configNode = $this->getOption( 'config-node' );
		$config = new Configuration(
			AutoLoader::makePath( static::getMaintenanceDir(), '..', 'config_defaults.php' ),
			$configFile,
			$configNode,
			true
		);
		Logger::init(
			$config->val( 'logging/root-context' ) . '-' . end( explode( "\\", $maintClass ) ),
			$config->val( 'logging/log-level' ),
			$config
		);
		Logger::getContext()->addLogStream( new ConsoleLogStream() );

		Logger::enterContext( Context::get()->getContextId() );

		set_error_handler( '\SmashPig\Maintenance\MaintenanceBase::lastChanceErrorHandler' );
		set_exception_handler( '\SmashPig\Maintenance\MaintenanceBase::lastChanceExceptionHandler' );

		// --- Load additional namespaces into the autoloader ---
		AutoLoader::getInstance()->addConfiguredIncludePaths();
		AutoLoader::getInstance()->addConfiguredNamespaces();
		AutoLoader::getInstance()->addConfiguredIncludes();
	}

	/**
	 * Do the actual work of the script.
	 */
	abstract public function execute();

	/* === Command line parsing === */

	/**
	 * Adds the default parameters to the expected command line inputs.
	 */
	protected function addDefaultParams() {
		$this->addOption( 'help', 'Display this help message', null, 'h' );
		$this->addOption( 'config-file', 'Path to additional configuration file', false );
		$this->addOption( 'config-node', 'Specific configuration node to load, if not default', 'default' );
		$this->addOption( 'memory-limit', 'Set a specific memory limit for the script, "max" for no limit', 'default' );
	}

	/**
	 * Add a parameter to the script. Will be displayed on --help
	 * with the associated description
	 *
	 * @param string $name 			Long name of the param, used with -- (ie help, version, etc)
	 * @param string $description 	Description of the param to show on --help
	 * @param mixed  $default		Value given back to the script if no argument is given. If this remains null
	 * 								the option is treated as a boolean with no argument expected; returning true
	 * 								if the option is present in the command line string.
	 * @param string $alias 		Optional character to use as short name, used with -
	 */
	protected function addOption( $name, $description, $default = null, $alias = false ) {
		if ( in_array( $name, $this->desiredOptions ) ) {
			throw new \SmashPig\Core\SmashPigException( "Option '$name' already exists. Cannot add again." );
		}
		$this->desiredOptions[$name] = array(
			'desc' => $description,
			'default' => $default,
			'alias' => $alias,
		);

		if ( $alias ) {
			if ( in_array( $alias, $this->aliasParamsMap ) ) {
				throw new \SmashPig\Core\SmashPigException( "Option '$name' cannot take alias '$alias'. Already in use." );
			}
			$this->aliasParamsMap[$alias] = $name;
		}
	}

	/**
	 * Checks to see if a particular option was explicitly provided.
	 *
	 * @param $name Name of option
	 *
	 * @return bool True if explicitly provided
	 */
	protected function optionProvided( $name ) {
		return isset( $this->options[$name] );
	}

	/**
	 * Get the value of a given option. Will return $default if provided and the option was
	 * not explicitly set, else will return the default set when the option was created.
	 *
	 * @param string $name		Name of the option to retrieve
	 * @param null 	 $default	Optional default override for the option
	 *
	 * @return mixed Value of the option or null if no default was provided
	 */
	protected function getOption( $name, $default = null ) {
		$value = null;

		if ( $this->optionProvided( ( $name ) ) ) {
			$value = $this->options[$name];
		} elseif ( $default ) {
			$value = $default;
		} else {
			$value = $this->desiredOptions[$name]['default'];
		}

		return trim( $value, "\" '\t\n\r\0\x0B" ); // The response from everything unfriendly
	}

	/**
	 * Adds a numbered argument that can be parsed out of the command line string.
	 *
	 * @param string $arg           Name of the argument, like 'start'
	 * @param string $description   Description of the argument
	 * @param bool   $required      If true and the argument is not provided, will not execute the script. Instead
	 *                              will display the help message.
	 *
	 * @throws SmashPigException if an argument is required after an optional argument
	 */
	protected function addArgument( $arg, $description, $required = true ) {

		$last = end( $this->expectedArguments );
		reset( $this->expectedArguments );

		if ( ( $last !== false ) && ( $last['required'] == false ) && $required ) {
			throw new SmashPigException( "May not add a required argument after optional arguments already in the stack." );
		}

		$this->expectedArguments[] = array(
			'name' => $arg,
			'desc' => $description,
			'required' => $required
		);
	}

	/**
	 * Determine if a given argument exists.
	 *
	 * @param int|string $id 	If an integer, 0 will return the first unnamed argument given. If
	 * 							a string, will return that named argument.
	 *
	 * @returns bool True if there is an argument in that position
	 */
	protected function hasArgument( $id = 0 ) {
		return isset( $this->args[$id] );
	}

	/**
	 * Get the value of a given argument. May be a name or numeric position.
	 *
	 * @param int|string $id 	If an integer, 0 will return the first unnamed argument given. If
	 * 							a string, will return that named argument.
	 * @param mixed	$default	Default value to return if argument does not exist.
	 *
	 * @return mixed
	 */
	protected function getArgument( $id, $default = null ) {
		return $this->hasArgument( $id ) ? $this->args[$id] : $default;
	}

	/**
	 * Parses command line arguments into @see $options and @see $args
	 */
	public function loadParamsAndArgs() {
		// No point in running this again and potentially corrupting things
		if ( $this->inputLoaded ) {
			return;
		}

		global $argv;
		$argv_local = $argv;

		$this->scriptName = array_shift( $argv_local );

		$startedArgs = false;

		// Iterate through the passed argv and load options/arguments.
		for ( $arg = reset( $argv_local ); $arg !== false; $arg = next( $argv_local ) ) {
			if ( !$startedArgs && ( substr( $arg, 0, 1 ) == '-' ) ) {
				// Option! Get the name of the option
				if ( substr( $arg, 0, 2 ) == '--' ) {
					// This is a long option name
					$option = substr( $arg, 2 );
				} else {
					// Short option name; find long name from alias map
					$option = substr( $arg, 1 );
					if ( !array_key_exists( $option, $this->aliasParamsMap ) ||
						 !array_key_exists( $this->aliasParamsMap[$option], $this->desiredOptions )
					) {
						print( "\nERROR: $option parameter was not expected\n" );
						$this->helpIfRequested( true );
					} else {
						$option = $this->aliasParamsMap[$option];
					}
				}

				if ( array_key_exists( $option, $this->options ) ) {
					print( "\nERROR: $option parameter given twice!\n" );
					$this->helpIfRequested( true );
				}

				// Get the key value (if it's supposed to exist, otherwise set it to true)
				if ( array_key_exists( $option, $this->desiredOptions ) ) {
					if ( $this->desiredOptions[$option]['default'] !== null ) {
						// Expecting parameter
						$param = next( $argv_local );
						if ( $param === false ) {
							print( "\nERROR: $option parameter requires a value\n" );
							$this->helpIfRequested( true );
						}
					} else {
						// No parameter expected, so we mark this option as present
						$param = true;
					}

					$this->options[$option] = $param;

				} else {
					print( "\nERROR: $option parameter was not expected\n" );
					$this->helpIfRequested( true );
				}
			} else {
				// Argument!
				$startedArgs = true;
				$this->args[] = $arg;
			}
		}

		// Validate number of required arguments
		$count = 0;
		array_walk( $this->expectedArguments, function($el) use (&$count) { $count += $el['required'] ? 1 : 0; } );
		if ( count( $this->args ) < $count ) {
			print( "\nERROR: Script expects $count arguments." );
			$this->helpIfRequested( true );
		}

		$this->inputLoaded = true;
	}

	/* === Runtime I/O === */

	/**
	 * Return input from standard input.
	 *
	 * @param int $len The number of bytes to read from the stream. If null, this will return a handle
	 * 					to stdin. Maintenance::STDIN_ALL reads to the end of the stream.
	 * @return Mixed
	 */
	protected function getStdIn( $len = null ) {
		if ( $len == MaintenanceBase::STDIN_ALL ) {
			return file_get_contents( 'php://stdin' );
		}
		$f = fopen( 'php://stdin', 'rt' );
		if ( !$len ) {
			return $f;
		}
		$input = fgets( $f, $len );
		fclose( $f );
		return rtrim( $input );
	}

	/**
	 * Prompt the console for input
	 * @param $prompt String what to begin the line with, like '> '
	 * @return String|bool response if given, false if terminated
	 */
	public static function readConsole( $prompt = '> ' ) {
		static $isatty = null;
		if ( is_null( $isatty ) ) {
			$isatty = self::posix_isatty( 0 /*STDIN*/ );
		}

		if ( $isatty && function_exists( 'readline' ) ) {
			return readline( $prompt );
		} else {
			if ( feof( STDIN ) ) {
				$st = false;
			} elseif ( $isatty ) {
				// Fallback... we'll have no editing controls, EWWW
				print $prompt;
				$st = fgets( STDIN, 1024 );
			} else {
				$st = fgets( STDIN, 1024 );
			}
			if ( $st === false ) return false;
			$resp = trim( $st );
			return $resp;
		}
	}

	/**
	 * Writes an error message to the console
	 *
	 * @param string $string Message to write
	 * @param bool   $fatal  True if the script should exit immediately and set an error code
	 */
	public static function error( $string, $fatal = false ) {
		if ( $fatal ) {
			Logger::alert( $string );
			exit( 1 );
		} else {
			Logger::error( $string );
		}
	}

	/* === Default param processing === */

	/**
	 * --help -h
	 * Maybe show the help.
	 * @param $force boolean Whether to force the help to show, default false
	 */
	protected function helpIfRequested( $force = false ) {
		if( !$force && !$this->getOption( 'help' ) ) {
			return;
		}

		$screenWidth = 80; // TODO: Caculate this!
		$tab = "    ";
		$nameWidth = 8 * strlen( $tab );
		$namePad = str_pad( '', $nameWidth, ' ' );
		$descWidth = $screenWidth - $nameWidth;

		ksort( $this->desiredOptions );

		// Print description
		if ( $this->description ) {
			print( "\n" . $this->description . "\n" );
		}

		// Usage string
		print( "Usage {$this->scriptName} [OPTIONS] " );
		foreach ( $this->expectedArguments as $arg ) {
			if ( $arg['required'] ) {
				print( "<" . $arg['name'] ."> " );
			} else {
				print( "[" . $arg['name'] . "] " );
			}
		}
		print( "\n" );

		// Describe arguments
		if ( count( $this->expectedArguments ) > 0 ) {
			print( "\nArguments: \n" );
			foreach ( $this->expectedArguments as $arg ) {
				$str = $tab . $arg['name'];
				$str = str_pad( $str, $nameWidth - count( $str ), ' ' );
				if ( count( $str ) > $nameWidth ) {
					$str .= "\n" . str_pad( '', $nameWidth, ' ' );
				}
				$str .= wordwrap( $arg['desc'], $descWidth, "\n$namePad", true );
				print( $str . "\n" );
			}
		}

		// Describe options
		if ( count( $this->desiredOptions ) > 0 ) {
			print( "\nOptions: \n" );
			foreach ( $this->desiredOptions as $name => $opt ) {
				$str = $tab . '--' . $name;
				if ( $opt['alias'] ) {
					$str .= ', -' . $opt['alias'];
				}
				if ( $opt['default'] ) {
					$str .= ' <' . $opt['default'] . '>';
				}

				$str = str_pad( $str, $nameWidth - count( $str ), ' ' );
				if ( count( $str ) > $nameWidth ) {
					$str .= "\n" . str_pad( '', $nameWidth, ' ' );
				}
				$str .= wordwrap( $opt['desc'], $descWidth, "\n$namePad", true );
				print( $str . "\n" );
			}
		}

		die( 1 );
	}

	/**
	 * --memory-limit
	 * Set PHP's memory limit to what was passed in through the memory-limit command
	 * line argument. A value of 'max' will remove the limit. 'default' will keep
	 * what's in the ini file.
	 */
	protected function adjustMemoryLimit() {
		$limit = strtolower( $this->getOption( 'memory-limit' ) );
		if ( $limit == 'max' ) {
			$limit = -1; // no memory limit
		}
		if ( $limit != 'default' ) {
			ini_set( 'memory_limit', $limit );
		}
	}

	/* === Helper Functions === */

	/**
	 * Get the maintenance directory.
	 * @return string
	 */
	public static function getMaintenanceDir() {
		return dirname( __FILE__ );
	}

	/**
	 * Wrapper for posix_isatty()
	 * We default as considering stdin a tty (for nice readline methods)
	 * but treating stout as not a tty to avoid color codes
	 *
	 * @param $fd int File descriptor
	 * @return bool
	 */
	public static function posix_isatty( $fd ) {
		if ( function_exists( 'posix_isatty' ) ) {
			return posix_isatty( $fd );
		} else {
			return !$fd;
		}
	}

	public static function lastChanceErrorHandler( $errno, $errstr, $errfile = 'Unknown File',
		$errline = 'Unknown Line', $errcontext = null
	) {
		$str = "($errno) $errstr @ $errfile:$errline";
		MaintenanceBase::error( $str );
		Logger::alert( $str, $errcontext );

		return false;
	}

	/**
	 * Hook from set_exception_handler(). Will clear output data, set the HTTP status to 500: Internal Error
	 * and then die.
	 *
	 * @param \Exception $ex The uncaught exception
	 */
	public static function lastChanceExceptionHandler( $ex ) {
		MaintenanceBase::error(
			"{$ex->getMessage()} @ {$ex->getFile()}:{$ex->getLine()}.\n{$ex->getTraceAsString()}",
			true
		);
		Logger::alert( "Last chance exception handler fired.", null, $ex );
	}
}
