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
		// In sandbox we get additionalData with cvcResult and avsResult, but both are of the
		// 'no data provided' variety, so I'm skipping the mapAdditionalData for now.
		return $response;
	}

	protected function getPaymentDetailsStatusNormalizer() : StatusNormalizer {
		return new CreatePaymentStatus();
	}
}
