<?php

namespace SmashPig\PaymentProviders;

use SmashPig\Core\PaymentError;

/**
 * Class PaymentProviderResponse
 * @package SmashPig\PaymentProviders
 *
 * Skeleton of a standard Payment Provider API response.
 */
abstract class PaymentProviderResponse {

	/**
	 * array of errors returned
	 * @var PaymentError[]
	 */
	protected $errors = [];

	/**
	 * raw response sent back from payment provider
	 * @var mixed
	 */
	protected $rawResponse;

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
	 * @return mixed
	 */
	public function getRawResponse() {
		return $this->rawResponse;
	}

	/**
	 * @param mixed $rawResponse
	 * @return $this
	 */
	public function setRawResponse( $rawResponse ) {
		$this->rawResponse = $rawResponse;
		return $this;
	}

	/**
	 * @return PaymentError[]
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @param PaymentError[] $errors
	 * @return $this
	 */
	public function setErrors( $errors ) {
		$this->errors = $errors;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function hasErrors() {
		return count( $this->getErrors() ) > 0;
	}

	/**
	 * Convenience function to check for a specific error code
	 *
	 * @param string $errorCode one of the ErrorCode constants
	 * @return bool
	 */
	public function hasError( $errorCode ) {
		foreach ( $this->getErrors() as $error ) {
			if ( $error->getErrorCode() === $errorCode ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add error(s) to the stack
	 *
	 * @param PaymentError[]|PaymentError $errors
	 * @return $this
	 */
	public function addErrors( $errors ) {
		if ( !is_array( $errors ) ) {
			$errors = [ $errors ];
		}
		foreach ( $errors as $error ) {
			if ( !$this->hasError( $error->getErrorCode() ) ) {
				array_push( $this->errors, $error );
			}
		}
		return $this;
	}

	/**
	 * @return string
	 */
	public function getGatewayTxnId() {
		return $this->gateway_txn_id;
	}

	/**
	 * @param string $gateway_txn_id
	 * @return static
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
	 * @return static
	 */
	public function setStatus( $status ) {
		$this->status = $status;
		return $this;
	}

	/**
	 * Subclasses should implement this with appropriate logic to determine
	 * whether the API call completed successfully. This might be a check on
	 * the status code or just checking whether any errors exist.
	 *
	 * @return bool
	 */
	abstract public function isSuccessful();

	/**
	 * @return string
	 */
	public function getRawStatus() {
		return $this->rawStatus;
	}

	/**
	 * @param string $rawStatus
	 * @return static
	 */
	public function setRawStatus( $rawStatus ) {
		$this->rawStatus = $rawStatus;
		return $this;
	}
}
