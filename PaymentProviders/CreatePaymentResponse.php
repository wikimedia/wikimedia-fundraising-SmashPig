<?php

namespace SmashPig\PaymentProviders;

/**
 * Represents a newly-created payment. Contains all the properties of the
 * PaymentDetailResponse and an additional pair of properties for when we
 * need to redirect the donor.
 *
 * Class CreatePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class CreatePaymentResponse extends PaymentDetailResponse {

	/**
	 * URL that a user should be redirected to in order to complete the payment
	 *
	 * @var string|null
	 */
	protected $redirectUrl;

	/**
	 * Response for the donor details
	 *
	 * @var array
	 */
	protected $donor_details = [];

	/**
	 * Data to be passed along with the redirect
	 *
	 * @var array
	 */
	protected $redirectData = [];

	/**
	 * @return string|null
	 */
	public function getRedirectUrl(): ?string {
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
	 * Set donor details in Payment response
	 * @param array $donor
	 * @return void
	 */
	public function setDonorDetails( array $donor ): void {
		$this->donor_details = [
			'first_name' => $donor['firstName'],
			'last_name' => $donor['lastName'],
			'email' => $donor['email'],
			'phone' => $donor['phone']
		];
	}

	/**
	 * Get donor details from payment response
	 * @return array
	 */
	public function getDonorDetails(): array {
		return $this->donor_details;
	}
}
