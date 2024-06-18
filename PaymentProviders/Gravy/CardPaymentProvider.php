<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentSessionResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Validators\Validator;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;

class CardPaymentProvider extends PaymentProvider implements IPaymentProvider {

	/**
	 * @param array $params [gateway_session_id, amount, currency]
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ) : CreatePaymentResponse {
		try {
			// create our standard response object from the normalized response
			$createPaymentResponse = new createPaymentResponse();

			// extract out the validation of input out to a separate class
			$validator = new Validator();
			if ( $validator->createPaymentInputIsValid( $params ) ) {
				// map local params to external format, ideally only changing key names and minor input format transformations
				$gravyRequestMapper = new RequestMapper();
				$gravyCreatePaymentRequest = $gravyRequestMapper->mapToCardCreatePaymentRequest( $params );

				// dispatch api call to external API using mapped params
				$rawGravyCreatePaymentResponse = $this->api->createPayment( $gravyCreatePaymentRequest );

				// normalize gravy response
				$gravyResponseMapper = new ResponseMapper();
				$normalizedResponse = $gravyResponseMapper->mapFromCreatePaymentResponse( $rawGravyCreatePaymentResponse );

				// populate our standard response object from the normalized response
				// this could be extracted out to a factory as we do for dlocal
				$createPaymentResponse = GravyCreatePaymentResponseFactory::fromRawResponse( $normalizedResponse );
			} else {
				// it failed!
				$createPaymentResponse->setSuccessful( false );
				$createPaymentResponse->setStatus( 'Failed' );
			}
		} catch ( \Exception $e ) {
			// it threw an exception!
			$createPaymentResponse->setSuccessful( false );
			$createPaymentResponse->setStatus( 'Failed' );
		}

		return $createPaymentResponse;
	}

	public function createPaymentSession() : CreatePaymentSessionResponse {
		try {
			// dispatch api call to external API using mapped params
			$sessionResponse = new CreatePaymentSessionResponse();
			$rawResponse = $this->api->createPaymentSession();

			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = new ResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromCreatePaymentSessionResponse( $rawResponse );

			$sessionResponse = GravyCreatePaymentSessionResponseFactory::fromRawResponse( $normalizedResponse );
			return $sessionResponse;
		} catch ( \Exception $e ) {
			// it threw an exception!

			$sessionResponse->setStatus( 'Failed' );
		}

		return $sessionResponse;
	}

	public function approvePayment( array $params ) : ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}

}
