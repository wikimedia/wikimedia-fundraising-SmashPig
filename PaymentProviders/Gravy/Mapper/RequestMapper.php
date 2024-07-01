<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class RequestMapper {

	public function mapToCreatePaymentRequest( array $params ): array {
		$request = [
			'amount' => $this->convertAmountToGravyAmountFormat( $params['amount'] ),
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

	/**
	 * Gravy requires amounts to be sent over in cents.
	 *
	 * @see https://docs.gr4vy.com/reference/transactions/new-transaction
	 * @param string $amount
	 * @return float
	 */
	protected function convertAmountToGravyAmountFormat( string $amount ): float {
		return (float)$amount * 100;
	}

}
