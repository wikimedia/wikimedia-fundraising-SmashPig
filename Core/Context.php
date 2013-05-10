<?php
namespace SmashPig\Core;

/**
 * Global context object -- useful for managing global variables when
 * we don't actually want globals or dedicated static classes.
 *
 * @package SmashPig\Core
 */
class Context {
	protected static $instance;

	public static function init() {
		if ( !Context::$instance ) {
			Context::$instance = new Context();
		}
	}

	/**
	 * Obtains the current context object
	 * @return Context
	 */
	public static function get() {
		if ( Context::$instance === null ) {
			Context::init();
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

	protected $contextId;

	public function __construct( $cid = null ) {
		if ( !$cid ) {
			$this->contextId = sprintf( 'SPCID-%010d', mt_rand( 10000, pow( 2, 31 ) - 1 ) );
		} else {
			$this->contextId = $cid;
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
}