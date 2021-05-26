<?php

namespace SmashPig\PaymentProviders;

/**
 * Class PaymentMethodResponse
 * @package SmashPig\PaymentProviders
 * TODO: put the normalized list of payment methods here
 */
class PaymentMethodResponse extends PaymentProviderResponse {

	/**
	 * FIXME: maybe inherit from a less featureful class
	 *
	 * @return bool
	 */
	public function isSuccessful() {
		return true;
	}

}
