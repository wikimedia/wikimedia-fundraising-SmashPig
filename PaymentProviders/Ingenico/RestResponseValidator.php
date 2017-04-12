<?php
namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Http\HttpStatusValidator;
use Symfony\Component\HttpFoundation\Response;

class RestResponseValidator extends HttpStatusValidator {
	protected function getSuccessCodes() {
		$codes = parent::getSuccessCodes();
		$codes[] = Response::HTTP_NOT_FOUND; // also a valid response in REST-ese
		return $codes;
	}
}
