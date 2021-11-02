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
	public function isSuccessful(): bool {
		return $this->getStatus() === FinalStatus::CANCELLED;
	}
}
