<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentData\FinalStatus;

/**
 * Class CancelPaymentResponse
 * @package SmashPig\PaymentProviders
 */
class CancelPaymentResponse extends PaymentProviderResponse {

	/**
	 * @inheritDoc
	 */
	public function isSuccessful() {
		return $this->getStatus() === FinalStatus::CANCELLED;
	}
}
