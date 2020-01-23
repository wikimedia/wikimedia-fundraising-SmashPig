<?php

namespace SmashPig\PaymentProviders;

/**
 * Class PaymentProviderResponse
 * @package SmashPig\PaymentProviders
 */
class PaymentProviderResponse {

	/**
	 * array of errors returned
	 * @var array
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
	 */
	public function setRawResponse( $rawResponse ) {
		$this->rawResponse = $rawResponse;
	}

	/**
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @param array $errors
	 */
	public function setErrors( $errors ) {
		$this->errors = $errors;
	}

	/**
	 * @return bool
	 */
	public function hasErrors() {
		return count( $this->getErrors() ) > 0;
	}

	/**
	 * Add error(s) message(s) to the stack
	 * @param $errors
	 * @return $this
	 */
	public function addErrors( $errors ) {
		if ( is_array( $errors ) ) {
			$this->errors = array_merge( $this->errors, $errors );
		} else {
			array_push( $this->errors, $errors );
		}
	}

}
