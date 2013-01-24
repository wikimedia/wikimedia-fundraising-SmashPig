<?php namespace SmashPig\Core\Logging;

class ConsoleLogStream implements ILogStream {

	protected $context;

	protected $levelNames = array(
		LOG_ALERT => 'ALERT',
		LOG_ERR => 'ERROR',
		LOG_WARNING => 'WARNING',
		LOG_INFO => 'INFO',
		LOG_NOTICE => 'NOTICE',
		LOG_DEBUG => 'DEBUG',
	);

	/**
	 * Process a new event into the log stream.
	 *
	 * @param array     $msg   Message array; potentially with keys message, data, exception
	 * @param int       $level The RFC 5424 severity level
	 * @param string    $date  The ISO 8601 date string
	 */
	public function processEvent( $msg, $level, $date ) {
		$name = $this->levelNames[$level];

		if ( strlen( $msg['exception'] ) ) {
			$msgstr = $msg['message'] . "\n" . $msg['exception'];
		} else {
			$msgstr = $msg['message'];
		}

		print( "[{$name}] ({$this->context}) $msgstr\n" );
	}

	/**
	 * Notification callback that the log context has added a new child
	 *
	 * @param string $contextName Child fully qualified context name
	 * @param string $contextData Current (child) context log lines -- (msg, level, date) tuple
	 */
    public function enterContext( $contextName, &$contextData ) {
		$this->context = $contextName;
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
