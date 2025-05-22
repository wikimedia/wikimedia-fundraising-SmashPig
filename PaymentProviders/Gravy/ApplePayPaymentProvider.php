<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentSessionResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\ValidationException;

class ApplePayPaymentProvider extends PaymentProvider {

	public function __construct( $params ) {
		parent::__construct();
		$this->requestMapper = $this->providerConfiguration->object( $params['request-mapper'] );
		$this->responseMapper = $this->providerConfiguration->object( $params['response-mapper'] );
		$this->validator = $this->providerConfiguration->object( $params['validator'] );
	}

	public function createPaymentSession( array $params ): CreatePaymentSessionResponse {
		$sessionResponse = new CreatePaymentSessionResponse();
		try {
			// extract out the validation of input out to a separate class
			$validator = $this->getValidator();

			$validator->validateCreateSessionInput( $params );

			// dispatch api call to external API using mapped params
			$sessionResponse = new CreatePaymentSessionResponse();
			$rawResponse = $this->api->createPaymentSession( $params, 'apple' );

			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = $this->getResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromCreatePaymentSessionResponse( $rawResponse );

			$sessionResponse = GravyCreatePaymentSessionResponseFactory::fromNormalizedResponse( $normalizedResponse );
			return $sessionResponse;
		} catch ( ValidationException $e ) {
			// it threw an exception!
			GravyCreatePaymentSessionResponseFactory::handleValidationException( $sessionResponse, $e->getData() );
		} catch ( \UnexpectedValueException $e ) {
			// it threw an API exception that isn't validation!
			Logger::error( 'Processor failed to create new payment with response:' . $e->getMessage() );
			GravyCreatePaymentSessionResponseFactory::handleException( $sessionResponse, $e->getMessage(),
				$e->getCode() );
		}

		return $sessionResponse;
	}

	protected function getValidator(): PaymentProviderValidator {
		return $this->validator;
	}

	protected function getResponseMapper(): ResponseMapper {
		return $this->responseMapper;
	}

	protected function getRequestMapper(): RequestMapper {
		return $this->requestMapper;
	}
}
