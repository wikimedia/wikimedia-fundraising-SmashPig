<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;

class BankPaymentProvider extends PaymentProvider {

	public function __construct( $params ) {
		parent::__construct();
		$this->requestMapper = $this->providerConfiguration->object( $params['request-mapper'] );
		$this->responseMapper = $this->providerConfiguration->object( $params['response-mapper'] );
		$this->validator = $this->providerConfiguration->object( $params['validator'] );
	}

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
