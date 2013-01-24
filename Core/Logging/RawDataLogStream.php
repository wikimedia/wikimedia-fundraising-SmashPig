<?php namespace SmashPig\Core\Logging;

use SmashPig\Core\Configuration;

class RawDataLogStream implements ILogStream {
	/** @var string Stores the current context string -- used for identity */
	protected $context = '';

	protected $inContext = false;

	protected $contextSuffix = '';

	protected $directory = '';

	public function __construct() {
		$this->directory = Configuration::getDefaultConfig()->val( 'logging/log-streams/rawdata/directory' );
		$this->contextSuffix = Configuration::getDefaultConfig()->val( 'logging/log-streams/rawdata/context-suffix' );
	}

	/**
	 * Process a new event into the log stream.
	 *
	 * @param string    $msg   The canonical log message
	 * @param int       $level The RFC 5424 severity level
	 * @param string    $date  The ISO 8601 date string
	 */
	public function processEvent( $msg, $level, $date ) {
		if ( $this->inContext ) {
			$date = strftime( '%Y%m%d%H%M%S', strtotime( $date ) );

			$fname = $this->directory . $date . '.' . str_replace( '::', '-', $this->context );
			file_put_contents( $fname, implode( Logger::LOG_SEPARATOR, $msg ), LOCK_EX );
		}
	}

	/**
	 * Notification callback that the log context has added a new child
	 *
	 * @param string $contextName Child fully qualified context name
	 * @param string $contextData Current (child) context log lines -- (msg, level, date) tuple
	 */
	public function enterContext( $contextName, &$contextData ) {
		$this->context = $contextName;

		if ( substr( $contextName, -strlen( $this->contextSuffix ) ) == $this->contextSuffix ) {
			$this->inContext = true;
		} else {
			$this->inContext = false;
		}
	}

	/**
	 * Notification callback that the log context has changed its name
	 *
	 * @param string $oldName The old fully qualified context name
	 * @param string $newName The new fully qualified context name
	 */
	public function renameContext( $oldName, $newName ) {
		$this->context = $newName;
	}

	/**
	 * Notification callback that the log context is switching into the parent context
	 *
	 * @param string $contextName Current (child) fully qualified context name
	 * @param string $contextData Current (child) context log lines -- (msg, level, date) tuple
	 */
	public function leaveContext( $contextName, &$contextData ) {}

	/**
	 * Notification callback that the logging infrastructure is shutting down
	 */
	public function shutdown() {}
}
