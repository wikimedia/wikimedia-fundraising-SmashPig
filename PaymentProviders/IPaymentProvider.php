<?php


namespace SmashPig\PaymentProviders;

interface IPaymentProvider {
	/**
	 * @param $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( $params );

	/**
	 * @param $params
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( $params );
}
