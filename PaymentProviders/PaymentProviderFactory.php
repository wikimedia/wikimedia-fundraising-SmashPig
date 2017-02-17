<?php

namespace SmashPig\PaymentProviders;

use SmashPig\Core\Context;

/**
 * Instantiates payment provider classes
 * TODO: standard way to set credentials from config here, instead of
 * making other classes do it
 */
class PaymentProviderFactory {

	public static function getProviderForMethod( $paymentMethod ) {
		$config = Context::get()->getConfiguration();
		$node = "payment-provider/$paymentMethod";
		return $config->object( $node );
	}
}
