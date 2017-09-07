<?php namespace SmashPig\Core\Logging\LogStreams;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\LogContextHandler;
use SmashPig\Core\Logging\LogEvent;
use SmashPig\Core\MailHandler;

class FailmailLogStream implements ILogStream {

	/** @var LogContextHandler */
	protected $context;
	protected $contextName = '';

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
	 * Function called at startup/initialization of the log streamer so that
	 * it has access to the current context beyond context names.
	 *
	 * @param LogContextHandler $ch Context handler object
	 */
	public function registerContextHandler( LogContextHandler $ch ) {
		$this->context = $ch;
	}

	/**
	 * Process a new event into the log stream.
	 *
	 * @param LogEvent $event Event to process
	 */
	public function processEvent( LogEvent $event ) {
		if ( $event->level == LOG_ERR ) {
			$this->errorSeen = true;

		} elseif ( $event->level == LOG_ALERT ) {
			$this->errorSeen = true;

			$this->sendMail( $event->level, $this->context->getContextEntries( 0 ) );
		}
	}

	/**
	 * Notification callback that the log context has added a new child
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the name of the current context
	 */
	public function enterContext( $contextNames ) {
		$this->contextName = $this->context->createQualifiedContextName( $contextNames );
	}

	/**
	 * Notification callback that the log context has changed its name
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 * @param string $oldTopName The old name of the current context
	 */
	public function renameContext( $contextNames, $oldTopName ) {
		$this->contextName = $this->context->createQualifiedContextName( $contextNames );
	}

	/**
	 * Notification callback that the log context is switching into the parent context
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 */
	public function leaveContext( $contextNames ) {
		if ( $this->errorSeen ) {
			$this->sendMail( LOG_ERR, $this->context->getContextEntries( 0 ) );
		}
		$this->errorSeen = false;
	}

	/**
	 * Notification callback that the logging infrastructure is shutting down
	 */
	public function shutdown() {
		if ( $this->errorSeen ) {
			$this->sendMail( LOG_ERR, $this->context->getContextEntries( 0 ) );
		}
	}

	/**
	 * Sends a mail if it has not already been sent.
	 *
	 * @param int $level
	 * @param LogEvent[] $events
	 */
	protected function sendMail( $level, $events ) {
		if ( $this->mailSent ) {
			return;
		}

		$body = array( "A problem has developed in SmashPig -- the available context is shown below. Data "
			. "objects are excluded for security but may be found in alternative log streams if configured.\n\n"
			. "NOTE: Additional errors may have occurred this session, but this email will only be sent "
			. "once. Check log streams for additional errors in this session.\n" );

		foreach ( $events as $event ) {
			$name = $this->levels[ $event->level ];
			if ( $event->tag ) {
				$body[] = sprintf(
					"%s %-9s (%s) %s",
					$event->datestring,
					$name,
					$event->tag,
					$event->message
				);
			} else {
				$body[] = sprintf(
					"%s %-9s %s",
					$event->datestring,
					$name,
					$event->message
				);
			}

			$exp = implode( "\n\t", $event->getExceptionBlob() );
			if ( $exp ) {
				$body[] = $exp;
			}
		}

		if ( $level == LOG_ALERT ) {
			$level = 'ALERT';
		} else {
			$level = 'ERROR';
		}

		$currentView = Context::get()->getProviderConfiguration()->getProviderName();

		MailHandler::sendEmail(
			$this->to,
			"FAILMAIL -- {$level} -- ({$currentView}) {$this->contextName}",
			implode( "\n", $body ),
			$this->from
		);

		$this->mailSent = true;
	}
}
