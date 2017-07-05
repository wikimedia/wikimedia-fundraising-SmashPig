<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Context;

/**
 * Base class for Ingenico payments. Each payment product group should get
 * a concrete subclass implementing PaymentProvider
 */
abstract class IngenicoPaymentProvider {

	protected $api;
	protected $providerConfiguration;

	public function __construct( $options = array() ) {
		// FIXME: provide objects in constructor
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}
}
