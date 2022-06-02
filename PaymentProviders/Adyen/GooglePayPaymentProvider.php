<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\CreatePaymentResponse;

class GooglePayPaymentProvider extends PaymentProvider {

	public function createPayment( array $params ) : CreatePaymentResponse {
		$rawResponse = $this->api->createGooglePayPayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		$this->mapStatus(
			$response,
			$rawResponse,
			new ApprovalNeededCreatePaymentStatus(),
			$rawResponse['resultCode'] ?? null
		);
		$this->mapRestIdAndErrors( $response, $rawResponse );
		// additionalData has the recurring details
		if ( isset( $rawResponse['additionalData'] ) ) {
			$this->mapAdditionalData( $rawResponse['additionalData'], $response );
		}

		return $response;
	}

	/**
	 * This method should never be called, since Google Pay does not include any flow in
	 * which the user is redirected to an external site and then returns to ours. (That's
	 * where a payment details status normalizer would be used.)
	 *
	 * {@inheritDoc}
	 * @see \SmashPig\PaymentProviders\Adyen\PaymentProvider::getPaymentDetailsStatusNormalizer()
	 */
	protected function getPaymentDetailsStatusNormalizer() : StatusNormalizer {
		throw new \BadMethodCallException( 'No payment details status normalizer for Google Pay.' );
	}

	protected function getPaymentDetailsSuccessfulStatuses(): array {
		throw new \BadMethodCallException( 'Unexpected getPaymentDetails call for Google Pay.' );
	}
}