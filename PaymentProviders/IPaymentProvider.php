<?php

namespace SmashPig\PaymentProviders;

interface IPaymentProvider {
	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse;

	/**
	 * @param array $params
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse;
}
