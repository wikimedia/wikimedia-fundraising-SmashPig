<?php


namespace SmashPig\PaymentProviders;

use SmashPig\PaymentData\FinalStatus;

/**
 * Class CreatePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class CreatePaymentResponse extends PaymentProviderResponse {
	/**
	 * Payment provider transaction ID
	 *
	 * https://www.mediawiki.org/wiki/Fundraising_tech/Transaction_IDs
	 * Also note the spelling: gateway_txn_id has no 'r' in txn. This is to maintain
	 * consistency with our queue messages and wmf_contribution_extra.gateway_txn_id
	 * column. Maybe one day we'll add the R.
	 *
	 * @var string
	 */
	protected $gateway_txn_id;

	/**
	 * mapped PaymentStatus status for the providers transaction status
	 * @var string
	 */
	protected $status;

	/**
	 * raw provider status in its original form.
	 * @var string
	 */
	protected $rawStatus;

	/**
	 * @return string
	 */
	public function getGatewayTxnId() {
		return $this->gateway_txn_id;
	}

	/**
	 * @param string $gateway_txn_id
	 * @return CreatePaymentResponse
	 */
	public function setGatewayTxnId( $gateway_txn_id ) {
		$this->gateway_txn_id = $gateway_txn_id;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param string $status
	 * @return CreatePaymentResponse
	 */
	public function setStatus( $status ) {
		$this->status = $status;
		return $this;
	}

	/**
	 * A successfully created payment should be in COMPLETE or PENDING_POKE status
	 *
	 * @return bool
	 */
	public function isSuccessful() {
		return in_array(
			$this->status,
			[
				FinalStatus::PENDING_POKE,
				FinalStatus::COMPLETE
			]
		);
	}

	/**
	 * @return string
	 */
	public function getRawStatus() {
		return $this->rawStatus;
	}

	/**
	 * @param string $rawStatus
	 * @return CreatePaymentResponse
	 */
	public function setRawStatus( $rawStatus ) {
		$this->rawStatus = $rawStatus;
		return $this;
	}
}
