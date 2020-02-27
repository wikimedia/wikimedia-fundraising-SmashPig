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
	 * Note: We should probably call this 'provider_trxn_id' but we already have enough
	 * transaction IDs across the stack.
	 * https://www.mediawiki.org/wiki/Fundraising_tech/Transaction_IDs
	 *
	 * @var mixed
	 */
	protected $gateway_trxn_id;

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
	 * @return mixed
	 */
	public function getGatewayTrxnId() {
		return $this->gateway_trxn_id;
	}

	/**
	 * @param mixed $gateway_trxn_id
	 * @return CreatePaymentResponse
	 */
	public function setGatewayTrxnId( $gateway_trxn_id ) {
		$this->gateway_trxn_id = $gateway_trxn_id;
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

	public function isSuccessful() {
		return $this->status === FinalStatus::COMPLETE;
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
