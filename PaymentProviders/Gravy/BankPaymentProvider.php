<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;

class BankPaymentProvider extends PaymentProvider {

	protected function getRequestMapper(): RequestMapper {
		return $this->requestMapper;
	}

	protected function getResponseMapper(): ResponseMapper {
		return $this->responseMapper;
	}

	protected function getValidator(): PaymentProviderValidator {
		return $this->validator;
	}
}
