<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Context;

/**
 * Base class for Ingenico payments. Each payment product group should get
 * a concrete subclass implementing PaymentProvider
 */
abstract class IngenicoPaymentProvider {

	protected $api;
	protected $config;

	public function __construct( $options = array() ) {
		// FIXME: provide objects in constructor
		$this->config = Context::get()->getConfiguration();
		$this->api = $this->config->object( 'api' );
	}
}
