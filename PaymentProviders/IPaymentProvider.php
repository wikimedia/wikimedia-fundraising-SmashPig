<?php


namespace SmashPig\PaymentProviders;

interface IPaymentProvider {
	/**
	 * @param $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse;

	/**
	 * @param $params
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse;
}
