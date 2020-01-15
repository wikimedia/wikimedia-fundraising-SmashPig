<?php


namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Adyen\WSDL\PaymentRequest;

interface PaymentProviderInterface {
	public function createPayment( $params );

	public function approvePayment( $paymentId, $params );

}
