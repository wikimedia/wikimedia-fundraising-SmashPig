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
		if ( is_array( $errors ) ) {
			$this->errors = array_merge( $this->errors, $errors );
		} else {
			array_push( $this->errors, $errors );
		}
		return $this;
	}

}
