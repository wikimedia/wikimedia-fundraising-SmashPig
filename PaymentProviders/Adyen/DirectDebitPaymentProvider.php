<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\CreatePaymentResponse;

class DirectDebitPaymentProvider extends PaymentProvider {

	/**
	 * Create an iDEAL payment with Adyen Checkout
	 * Initial payments will be of type iDEAL and subsequent will be SEPA Direct Debit
	 * https://docs.adyen.com/payment-methods/ideal/web-component
	 *
	 * @param array $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		// one time and initial recurrings will have an issuer_id set
		if ( !empty( $params['issuer_id'] ) ) {
			$rawResponse = $this->api->createDirectDebitPaymentFromCheckout( $params );
		} else {
			// subsequent recurrings will not have an issuer_id
			$params['payment_method'] = 'sepadirectdebit';
			$rawResponse = $this->api->createPaymentFromToken( $params );
		}
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );
		$rawStatus = $rawResponse['resultCode'];

		$this->mapStatus(
			$response,
			$rawResponse,
			new CreatePaymentStatus(),
			$rawStatus
		);

		if ( $rawStatus === 'RedirectShopper' ) {
			$response->setRedirectUrl( $rawResponse['action']['url'] );
		}
		$this->mapRestIdAndErrors( $response, $rawResponse );

		return $response;
	}

	protected function getPaymentDetailsStatusNormalizer(): StatusNormalizer {
		return new CreatePaymentStatus();
	}
}
