<?php namespace SmashPig\Core\Logging;

class SyslogLogStream implements ILogStream {
    /** @var string Stores the current context string -- used for identity */
    protected $context = '';

    /** @var int Facility code to log under -- from logging/syslog/facility */
    protected $facility;

    /** @var int Syslog options -- from logging/syslog/options */
    protected $options;

    public function __construct( $facility = LOG_LOCAL0, $options = LOG_NDELAY ) {
        $this->facility = $facility;
        $this->options = $options;
    }

    /**
     * Process a new event into the log stream.
     *
     * @param string    $msg   The canonical log message
     * @param int       $level The RFC 5424 severity level
     * @param string    $date  The ISO 8601 date string
     */
    public function processEvent( $msg, $level, $date ) {
        openlog( $this->context, $this->options, $this->facility );
        syslog( $level, implode( Logger::LOG_SEPARATOR, $msg ) );
        closelog();
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
