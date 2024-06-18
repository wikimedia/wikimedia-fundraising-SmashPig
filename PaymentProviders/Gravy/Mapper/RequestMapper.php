<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class RequestMapper {

	public function mapToCreatePaymentRequest( array $params ): array {
		$request = [
			'amount' => (float)$params['amount'] * 100,
			'currency' => $params['currency'],
			'country' => $params['country'],
			'payment_method' => [
				'method' => $params['method'] ?? '',
			],
			'external_identifier' => $params['order_id'],
		];

		if ( !empty( $params['recurring'] ) ) {
			$request['store'] = true;
			$request['payment_source'] = 'recurring';
		}

		if ( !empty( $params['redirect_url'] ) ) {
			$request['payment_method']['redirect_url'] = $params['redirect_url'];
		}

		return $request;
	}

	/**
	 * @return array
	 */
	public function mapToCardCreatePaymentRequest( array $params ): array {
		$request = $this->mapToCreatePaymentRequest( $params );

		$request['payment_method'] = array_merge( $request['payment_method'], [
			'method' => 'checkout-session',
			'id' => $params['gateway_session_id'],
		] );

		return $request;
	}

	/**
	 * @return array
	 */
	public function mapToCardApprovePaymentRequest(): array {
		$request = [];
		return $request;
	}

}
