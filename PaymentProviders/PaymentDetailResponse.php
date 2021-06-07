<?php

namespace SmashPig\PaymentProviders;

/**
 * Class PaymentMethodResponse
 * @package SmashPig\PaymentProvidersF
 */
class PaymentDetailResponse extends PaymentProviderResponse {

	/**
	 * FIXME: maybe inherit from a less featureful class (along with PaymentMethodResponse)
	 *
	 * @return bool
	 */
	public function isSuccessful() {
		return true;
	}

}
