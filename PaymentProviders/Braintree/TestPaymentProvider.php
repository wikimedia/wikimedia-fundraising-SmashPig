<?php

namespace SmashPig\PaymentProviders\Braintree;

class TestPaymentProvider extends PaymentProvider {

	public function ping(): string {
		$response = $this->api->ping();
		return $response['data']['ping'];
	}
}
