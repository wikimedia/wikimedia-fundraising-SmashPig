<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentData\FinalStatus;

/**
 * Class ApprovePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class ApprovePaymentResponse extends PaymentProviderResponse {

	/**
	 * A successfully approved payment should be in COMPLETE status.
	 *
	 * @return bool
	 */
	public function isSuccessful(): bool {
		return $this->getStatus() === FinalStatus::COMPLETE;
	}
}
