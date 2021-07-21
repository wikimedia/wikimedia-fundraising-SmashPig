<?php

namespace SmashPig\PaymentProviders;

class SavedPaymentDetailsResponse extends PaymentProviderResponse {

	/**
	 * @var SavedPaymentDetails[]
	 */
	protected $detailsList;

	public function isSuccessful() {
		return count( $this->getDetailsList() ) > 0;
	}

	/**
	 * @return SavedPaymentDetails[]
	 */
	public function getDetailsList(): array {
		return $this->detailsList;
	}

	/**
	 * @param SavedPaymentDetails[] $detailsList
	 * @return SavedPaymentDetailsResponse
	 */
	public function setDetailsList( array $detailsList ): SavedPaymentDetailsResponse {
		$this->detailsList = $detailsList;
		return $this;
	}

	/**
	 * Convenience function to get the first set of details in the list
	 * @return SavedPaymentDetails|null
	 */
	public function first(): ?SavedPaymentDetails {
		return $this->detailsList[0] ?? null;
	}
}
