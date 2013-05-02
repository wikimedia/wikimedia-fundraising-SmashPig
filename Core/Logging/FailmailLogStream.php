<?php namespace SmashPig\Core\Logging;

use SmashPig\Core\Configuration;
use SmashPig\Core\MailHandler;

class FailmailLogStream implements ILogStream {

	protected $context = '';
	protected $contextData;

	protected $errorSeen = false;
	protected $mailSent = false;

	protected $to;
	protected $from;

	protected $levels = array(
		LOG_ALERT   => '[ALERT]',
		LOG_ERR     => '[ERROR]',
		LOG_WARNING => '[WARNING]',
		LOG_INFO    => '[INFO]',
		LOG_NOTICE  => '[NOTICE]',
		LOG_DEBUG   => '[DEBUG]',
	);

	public function __construct( $toAddr, $fromAddr = null ) {
		$this->to = $toAddr;

		if ( $fromAddr ) {
			$this->from = $fromAddr;
		} else {
			$this->from = 'smashpig-failmail@' . gethostname();
		}
	}

	/**
	 * Process a new event into the log stream.
	 *
	 * @param array     $msg   Message array; potentially with keys message, data, exception
	 * @param int       $level The RFC 5424 severity level
	 * @param string    $date  The ISO 8601 date string
	 */
	public function processEvent( $msg, $level, $date ) {

		if ( $level == LOG_ERR ) {
			$this->errorSeen = true;

		} elseif ( $level == LOG_ALERT ) {
			$this->errorSeen = true;

			$this->sendMail( $level, $this->contextData );
		}
	}

	/**
	 * Notification callback that the log context has added a new child
	 *
	 * @param string $contextName Child fully qualified context name
	 */
	public function enterContext( $contextName, &$contextData ) {
		$this->context = $contextName;
		$this->contextData = & $contextData;
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
	public function leaveContext( $contextName, &$contextData ) {
		if ( $this->errorSeen ) {
			$this->sendMail( LOG_ERR, $this->contextData );
		}
		$this->errorSeen = false;
	}

	/**
	 * Notification callback that the logging infrastructure is shutting down
	 */
	public function shutdown() {
		if ( $this->errorSeen ) {
			$this->sendMail( LOG_ERR, $this->contextData );
		}
	}

	/**
	 * Sends a mail if it hasn't already been sent.
	 *
	 * @param $level
	 * @param $msgs
	 */
	protected function sendMail( $level, $msgs ) {
		if ( $this->mailSent ) {
			return;
		}

		$body = "A problem has developed in SmashPig -- the available context is shown below. Data objects "
			. "are excluded for security but may be found in alternative log streams if configured.\n\n"
			. "NOTE: Additional errors may have occurred this session, but this email will only be sent "
			. "once. Check log streams for additional errors in this session.\n";

		foreach ( $msgs as $msg ) {
			$body .= "\n" . $msg[ 2 ] . " " . $this->levels[ $msg[ 1 ] ] . " " . $msg[ 0 ][ 'message' ];
			if ( strlen( $msg[ 0 ][ 'exception' ] ) ) {
				$body .= "\n--" . $msg[ 0 ][ 'exception' ] . "\n--\n";
			}
		}

		if ( $level == LOG_ALERT ) {
			$level = 'ALERT';
		} else {
			$level = 'ERROR';
		}

		MailHandler::sendEmail(
			$this->to,
			"FAILMAIL -- {$level} -- {$this->context}",
			$body,
			$this->from
		);

		$this->mailSent = true;
	}
}
