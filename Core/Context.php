<?php
namespace SmashPig\Core;

use SmashPig\Core\Logging\Logger;

/**
 * Global context object -- useful for managing global variables when
 * we don't actually want globals or dedicated static classes.
 *
 * @package SmashPig\Core
 */
class Context {
	/** @var Context Reference to the current global context */
	protected static $instance;
	protected static $loggerInitialized = false;
	protected $contextId;
	protected $sourceRevision = 'unknown';

	/** @var Configuration|null Reference to the context configuration object */
	protected $config = null;

	public static function init( Configuration $config ) {
		if ( !Context::$instance ) {
			Context::$instance = new Context();
			Context::$instance->setConfiguration( $config );
		}
	}

	public static function initWithLogger(
		Configuration $config,
		$loggerPrefix = ''
	) {
		self::init( $config );
		if ( !self::$loggerInitialized ) {
			// FIXME: Terminate logger crap with extreme prejudice
			Logger::init(
				$config->val( 'logging/root-context' ),
				$config->val( 'logging/log-level' ),
				$config,
				$loggerPrefix
			);
			self::$loggerInitialized = true;
		}
	}

	/**
	 * Obtains the current context object
	 * @return Context
	 */
	public static function get() {
		if ( Context::$instance === null ) {
			// Remove this once we know we aren't going to blow up
			Logger::notice(
				'Context being initialized as part of get() request. Normally should use init() first.',
				debug_backtrace( null )
			);
			Context::init( Configuration::getDefaultConfig() );
		}
		return Context::$instance;
	}

	/**
	 * Sets the current context, returning the displaced context
	 * @param Context $c
	 * @return Context
	 */
	public static function set( Context $c = null ) {
		$old = Context::$instance;
		Context::$instance = $c;

		return $old;
	}

	public function __construct( $cid = null ) {
		if ( !$cid ) {
			$this->contextId = sprintf( 'SPCID-%010d', mt_rand( 10000, pow( 2, 31 ) - 1 ) );
		} else {
			$this->contextId = $cid;
		}

		$versionStampPath = __DIR__ . "/../.version-stamp";
		if ( file_exists( $versionStampPath ) ) {
			$versionId = file_get_contents( $versionStampPath );
			if ( $versionId !== false ) {
				$this->sourceRevision = trim( $versionId );
			}
		}
	}

	/**
	 * Gets the global context identifier - this is used for logging, filenames,
	 * or other identifiers specific to the current job.
	 *
	 * @return string Format of SPCID-[1-9][0-9]{8}
	 */
	public function getContextId() {
		return $this->contextId;
	}

	public function getSourceRevision() {
		return $this->sourceRevision;
	}

	/**
	 * Sets a configuration object associated with this context.
	 *
	 * All calls to get configuration options need to happen through the
	 * context object; which means that if we ever support changing configurations
	 * based on account; this will somehow require us to support either
	 * stacked configurations or stacked contexts...
	 *
	 * @param Configuration $config
	 */
	protected function setConfiguration( Configuration $config ) {
		$this->config = $config;
	}

	/**
	 * Gets the configuration object associated with the current context.
	 *
	 * Set the configuration using init()
	 *
	 * Use this instead of Configuration::getDefaultConfig();
	 *
	 * @return null|Configuration
	 */
	public function getConfiguration() {
		if ( $this->config ) {
			return $this->config;
		} else {
			Logger::notice(
				'Context returning default configuration. Probably missing a setConfiguration().',
				debug_backtrace( null )
			);
			return Configuration::getDefaultConfig();
		}
	}
}
