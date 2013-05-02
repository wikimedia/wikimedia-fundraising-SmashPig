<?php namespace SmashPig\Core\Logging;

use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\JsonSerializableObject;

class Logger {
	/** @var bool True if the init() call has been made */
	protected static $log_init = false;

	/** @var int The log level must be greater than this to be processed. */
	protected static $threshold = LOG_DEBUG;

	/**
	 * Initialize the logging framework.
	 *
	 * Will add all LogStreams registered under logging/enabled-log-streams. These
	 * handlers must have class instantiation entries under logging/log-streams/<name>.
	 *
	 * @param $name            Root context name
	 * @param $threshold       Minimum log level to record into the context
	 */
	static function init( $name, $threshold, Configuration $config ) {
		// Basic init
		if ( !self::$log_init ) {
			self::renameContext( $name, false );
			self::$log_init = true;
			register_shutdown_function( 'SmashPig\\Core\\Logging\\Logger::close' );
		} else {
			self::renameContext( $name, true );
		}

		self::$threshold = $threshold;

		// Init all the log streams
		try {
			$streams = $config->val( 'logging/enabled-log-streams' );

			foreach ( $streams as $streamName ) {
				self::addLogStream( $config->obj( "logging/log-streams/$streamName", false ) );
			}

		} catch ( \Exception $ex ) {
			trigger_error(
				"Exception while creating default log streams: {$ex->getMessage()} at {$ex->getTraceAsString()}",
				E_USER_ERROR
			);
			die();
		}
	}

	/**
	 * register_shutdown_function callback. Call on shutdown only!
	 */
	static function close() {
		while ( count( self::$contextStack ) > 1 ) {
			self::logStreamLeaveContext( self::$contextString, self::$contextData[ 0 ] );
			self::leaveContext();
		}
		self::logStreamLeaveContext( self::$contextString, self::$contextData[ 0 ] );
		self::logStreamShutdown();
	}

	/* === EVENT HANDLING === */
	/** String used to separate log fields */
	const LOG_SEPARATOR = ' | ';

	/**
	 * Log an immediate/critical failure. Will be immediately forwarded to the designated
	 * error contact. Use this for things like database failures, top of PHP error stack
	 * exceptions, and non recoverable errors like being unable to requeue a message.
	 *
	 * @param string           $msg         Message string to log
	 * @param null|object      $data        Serializable data object relevant to the event, if any
	 * @param null|\Exception  $ex          Exception object relevant to the event, if any
	 */
	public static function alert( $msg, $data = null, $ex = null ) {
		self::processEvent( $msg, LOG_ALERT, date( 'c' ), $data, $ex );
	}

	/**
	 * Log a non-urgent failure. Will be forwarded onto the designated error contact, but
	 * may be digested/filtered in some way. Use this for malformed data, and recoverable
	 * exceptions (ie: a queued message could not be processed but could be requeued.)
	 *
	 * @param string           $msg         Message string to log
	 * @param null|object      $data        Serializable data object relevant to the event, if any
	 * @param null|\Exception  $ex          Exception object relevant to the event, if any
	 */
	public static function error( $msg, $data = null, $ex = null ) {
		self::processEvent( $msg, LOG_ERR, date( 'c' ), $data, $ex );
	}

	/**
	 * Log a warning message, NOT AN ERROR, but indication that an error may occur if action
	 * is not taken, e.g. file system 85% full; db lag > 5s; APC/MemCache unavailable; etc
	 *
	 * @param string           $msg         Message string to log
	 * @param null|object      $data        Serializable data object relevant to the event, if any
	 * @param null|\Exception  $ex          Exception object relevant to the event, if any
	 */
	public static function warning( $msg, $data = null, $ex = null ) {
		self::processEvent( $msg, LOG_WARNING, date( 'c' ), $data, $ex );
	}

	/**
	 * Log an event that is unusual but IS NOT an error condition - might be summarized in an
	 * email to developers or admins to spot potential problems - no immediate action required.
	 *
	 * @param string           $msg         Message string to log
	 * @param null|object      $data        Serializable data object relevant to the event, if any
	 * @param null|\Exception  $ex          Exception object relevant to the event, if any
	 */
	public static function notice( $msg, $data = null, $ex = null ) {
		self::processEvent( $msg, LOG_NOTICE, date( 'c' ), $data, $ex );
	}

	/**
	 * Log information in the course of normal operational - may be harvested for reporting,
	 * measuring throughput, etc. - no action required.
	 *
	 * @param string           $msg         Message string to log
	 * @param null|object      $data        Serializable data object relevant to the event, if any
	 * @param null|\Exception  $ex          Exception object relevant to the event, if any
	 */
	public static function info( $msg, $data = null, $ex = null ) {
		self::processEvent( $msg, LOG_INFO, date( 'c' ), $data, $ex );
	}

	/**
	 * Log information useful to developers for debugging the application; not useful
	 * during normal operation.
	 *
	 * @param string           $msg         Message string to log
	 * @param null|object      $data        Serializable data object relevant to the event, if any
	 * @param null|\Exception  $ex          Exception object relevant to the event, if any
	 */
	public static function debug( $msg, $data = null, $ex = null ) {
		self::processEvent( $msg, LOG_DEBUG, date( 'c' ), $data, $ex );
	}

	/**
	 * Creates a unified log message string from all provided parts and logs to the given
	 * context.
	 *
	 * @param string           $msg         Message string to log
	 * @param int              $level       RFC 5424 severity level of this event
	 * @param string           $date        Date string of the event, should be ISO 8601
	 * @param null|object      $data        Serializable data object relevant to the event, if any
	 * @param null|\Exception  $ex          Exception object relevant to the event, if any
	 */
	protected static function processEvent( $msg, $level, $date, $data = null, \Exception $ex = null ) {
		if ( $level > self::$threshold ) {
			return;
		}

		$msg = array( 'message' => $msg );
		if ( $data ) {
			if ( $data instanceof JsonSerializableObject ) {
				$jdata = $data->toJson();
			} else {
				$jdata = json_encode( $data );
			}

			if ( $jdata ) {
				$msg[ 'data' ] = $jdata;
			} else {
				$msg[ 'data' ] = '!!NON SERIALIZABLE DATA!!';
			}
		} else {
			$msg[ 'data' ] = '';
		}

		if ( $ex ) {
			$exar = array();

			$iex = $ex;
			while ( $iex !== null ) {
				$exar[ ] = "{$ex->getMessage()}\n{$ex->getTraceAsString()}";
				$iex = $ex->getPrevious();
			}

			$msg[ 'exception' ] = implode( '\n\nCaused by ', $exar );
		} else {
			$msg[ 'exception' ] = '';
		}

		self::addEventToContext( $msg, $level, $date );
		self::logStreamEventProcess( $msg, $level, $date );
	}

	/* === CONTEXT HANDLING === */
	const DEFAULT_CONTEXT_NAME = 'ContextNotInitialized';

	/** @var array Stack of strings holding context names */
	private static $contextStack = array( self::DEFAULT_CONTEXT_NAME );

	/** @var array Stack of stacks holding all log lines for a context */
	private static $contextData = array( array() );

	/** @var string Cached fully qualified context string */
	private static $contextString = 'ContextNotInitialized';

	/**
	 * Renames the current logging context. Effects the log prefix used for all
	 * events under this context. May have adverse effects on logstreams that log
	 * in real time (IE: Syslog) because they will have logged items under the old
	 * context name.
	 *
	 * @param string   $newName     New name for the current context
	 * @param bool     $addLogEntry If false will not create a log line stating the name change
	 *
	 * @return string The old name of this context
	 */
	public static function renameContext( $newName, $addLogEntry = true ) {
		$old = self::$contextStack[ 0 ];
		$oldFQ = self::$contextString;

		if ( $addLogEntry ) {
			self::info( "Renaming logging context '{$old}' to '{$newName}'." );
		}

		self::$contextStack[ 0 ] = $newName;
		self::$contextString = self::createQualifiedContextName();

		self::logStreamRenameContext( $oldFQ, self::$contextString );

		return $old;
	}

	/**
	 * Enters a new context with the current context as its parent.
	 *
	 * @param string $name Child context name
	 */
	public static function enterContext( $name ) {
		self::info( "Entering new logging context '{$name}'." );

		array_unshift( self::$contextStack, $name );
		self::$contextString = self::createQualifiedContextName();
		array_unshift( self::$contextData, array() );

		self::logStreamEnterContext( self::$contextString, self::$contextData[ 0 ] );
	}

	/**
	 * Leaves the current context for the parent context. You may not leave the root
	 * context.
	 *
	 * Side effects include removing all stored log lines for this context. Before this
	 * happens all LogStreams have the opportunity to do last chance processing.
	 *
	 * @return string The current context name, or '' if this is the root context
	 */
	public static function leaveContext() {
		if ( count( self::$contextStack ) > 1 ) {
			self::logStreamLeaveContext( self::$contextString, self::$contextData[ 0 ] );

			$old = array_shift( self::$contextStack );
			self::$contextString = self::createQualifiedContextName();
			array_shift( self::$contextData );

			self::logStreamEnterContext( self::$contextString, self::$contextData[ 0 ] );
		} else {
			$old = '';
		}

		return $old;
	}

	/**
	 * Creates the fully qualified context name from the current stack. Individual nodes
	 * are separated by '::'.
	 *
	 * @return string The fully qualified context name.
	 */
	protected static function createQualifiedContextName() {
		return implode( '::', array_reverse( self::$contextStack ) );
	}

	/**
	 * Adds an event to the current context stack
	 *
	 * @param string     $msg      Message array; potentially with keys message, data, exception
	 * @param int        $level    RFC level
	 * @param string     $date     ISO date
	 */
	protected static function addEventToContext( $msg, $level, $date ) {
		self::$contextData[ 0 ][ ] = array( $msg, $level, $date );
	}

	/* === Log Stream Management === */
	/** @var array List of LogStreams that consume log events */
	protected static $logStreams = array();

	/**
	 * Adds a LogStream log event processor
	 *
	 * @param ILogStream $stream
	 */
	public static function addLogStream( ILogStream $stream ) {
		self::$logStreams[ ] = $stream;
		$stream->enterContext( self::$contextString, self::$contextData[ 0 ] );
	}

	/**
	 * Calls the event_process() function in each registered LogStream
	 */
	protected static function logStreamEventProcess( $msg, $level, $date ) {
		foreach ( self::$logStreams as $stream ) {
			$stream->processEvent( $msg, $level, $date );
		}
	}

	/**
	 * Calls the enter_context() function in each registered LogStream
	 */
	protected static function logStreamEnterContext( $contextName, &$contextData ) {
		foreach ( self::$logStreams as $stream ) {
			$stream->enterContext( $contextName, $contextData );
		}
	}

	/**
	 * Calls the rename_context() function in each registered LogStream
	 */
	protected static function logStreamRenameContext( $oldContextName, $newContextName ) {
		foreach ( self::$logStreams as $stream ) {
			$stream->renameContext( $oldContextName, $newContextName );
		}
	}

	/**
	 * Calls the leave_context() function in each registered LogStream
	 */
	protected static function logStreamLeaveContext( $contextName, &$contextData ) {
		foreach ( self::$logStreams as $stream ) {
			$stream->leaveContext( $contextName, $contextData );
		}
	}

	/**
	 * Calls the shutdown() function in each registered LogStream
	 */
	protected static function logStreamShutdown() {
		foreach ( self::$logStreams as $stream ) {
			$stream->shutdown();
		}
	}
}
