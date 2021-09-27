<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\CreatePaymentResponse;

class ApplePayPaymentProvider extends PaymentProvider {

	public function createPayment( array $params ) : CreatePaymentResponse {
		$rawResponse = $this->api->createApplePayPayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		$this->mapStatus(
			$response,
			$rawResponse,
			new CreatePaymentStatus(),
			$rawResponse['resultCode'] ?? null
		);
		$this->mapRestIdAndErrors( $response, $rawResponse );
		// additionalData has the recurring details
		if ( isset( $rawResponse['additionalData'] ) ) {
			$this->mapAdditionalData( $rawResponse['additionalData'], $response );
		}

		return $response;
	}

	protected function getPaymentDetailsStatusNormalizer() : StatusNormalizer {
		return new CreatePaymentStatus();
	}
}
