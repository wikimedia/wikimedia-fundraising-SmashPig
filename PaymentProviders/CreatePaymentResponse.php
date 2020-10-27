<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentData\FinalStatus;

/**
 * Class CreatePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class CreatePaymentResponse extends PaymentProviderResponse {

	/**
	 * A successfully created payment should be in COMPLETE or PENDING_POKE status
	 *
	 * @return bool
	 */
	public function isSuccessful() {
		return in_array(
			$this->getStatus(),
			[
				FinalStatus::PENDING_POKE,
				FinalStatus::COMPLETE
			]
		);
	}

	/**
	 * Determines whether the payment is in a status that requires further
	 * action from the merchant to push through. Generally this means a card
	 * payment has been authorized but not yet captured.
	 *
	 * @return bool
	 */
	public function requiresApproval() {
		return $this->getStatus() === FinalStatus::PENDING_POKE;
	}
}
