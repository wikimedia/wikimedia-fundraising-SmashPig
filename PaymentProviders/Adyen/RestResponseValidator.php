<?php
namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Http\HttpStatusValidator;
use Symfony\Component\HttpFoundation\Response;

class RestResponseValidator extends HttpStatusValidator {
	protected function getSuccessCodes(): array {
		$codes = parent::getSuccessCodes();
		// Adyen uses 422 when an invalid card number or CVC is sent
		$codes[] = Response::HTTP_UNPROCESSABLE_ENTITY;
		return $codes;
	}
}
