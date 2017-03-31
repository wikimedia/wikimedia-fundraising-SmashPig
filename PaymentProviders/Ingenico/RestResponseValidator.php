<?php
namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Http\HttpStatusValidator;

class RestResponseValidator extends HttpStatusValidator {
	protected function getSuccessCodes() {
		$codes = parent::getSuccessCodes();
		$codes[] = '404'; // also a valid response in REST-ese
		return $codes;
	}
}
