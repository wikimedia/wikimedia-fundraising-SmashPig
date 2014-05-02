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

	/**
	 * Obtains the current context object
	 * @return Context
	 */
	public static function get() {
		if ( Context::$instance === null ) {
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
		Context::$instance = ( $c === null ) ? new Context() : $c;

		return $old;
	}

	public function __construct( $cid = null ) {
		if ( !$cid ) {
			$this->contextId = sprintf( 'SPCID-%010d', mt_rand( 10000, pow( 2, 31 ) - 1 ) );
		} else {
			$this->contextId = $cid;
		}

		$versionStampPath = __DIR__ . "/../.version-stamp";
		$versionId = file_get_contents( $versionStampPath );
		if ( $versionId !== false ) {
			$this->sourceRevision = trim( $versionId );
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

	public function setConfiguration( Configuration $config ) {
		$this->config = $config;
	}

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
