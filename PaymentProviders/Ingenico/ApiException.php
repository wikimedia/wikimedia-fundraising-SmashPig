<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\SmashPigException;

class ApiException extends SmashPigException {

	protected $rawErrors;

	public function setRawErrors( $errors ) {
		$this->rawErrors = $errors;
	}

	public function getRawErrors() {
		return $this->rawErrors;
	}
}
