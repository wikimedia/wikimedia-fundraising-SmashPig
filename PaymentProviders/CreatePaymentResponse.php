<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentData\FinalStatus;

/**
 * Class CreatePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class CreatePaymentResponse extends PaymentProviderResponse {

	/**
	 * Keys are types of risk scores (e.g. 'cvv' and 'avs') and values are
	 * numbers from 0-100 indicating how likely the authorization is fraudulent.
	 *
	 * @var array
	 */
	protected $riskScores = [];

	/**
	 * URL that a user should be redirected to in order to complete the payment
	 *
	 * @var string|null
	 */
	protected $redirectUrl;

	/**
	 * Data to be passed along with the redirect
	 *
	 * @var array
	 */
	protected $redirectData = [];

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
	 * @return string|null
	 */
	public function getRedirectUrl(): string {
		return $this->redirectUrl;
	}

	/**
	 * @param string $redirectUrl
	 * @return CreatePaymentResponse
	 */
	public function setRedirectUrl( string $redirectUrl ): CreatePaymentResponse {
		$this->redirectUrl = $redirectUrl;
		return $this;
	}

	public function requiresRedirect(): bool {
		return !empty( $this->redirectUrl );
	}

	/**
	 * @return array
	 */
	public function getRedirectData(): array {
		return $this->redirectData;
	}

	/**
	 * @param array $redirectData
	 * @return CreatePaymentResponse
	 */
	public function setRedirectData( array $redirectData ): CreatePaymentResponse {
		$this->redirectData = $redirectData;
		return $this;
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

	/**
	 * @return array
	 */
	public function getRiskScores(): array {
		return $this->riskScores;
	}

	/**
	 * @param array $riskScores
	 * @return static
	 */
	public function setRiskScores( array $riskScores ): CreatePaymentResponse {
		$this->riskScores = $riskScores;
		return $this;
	}
}
