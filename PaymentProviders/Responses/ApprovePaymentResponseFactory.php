<?php

namespace SmashPig\PaymentProviders\Responses;

abstract class ApprovePaymentResponseFactory {
	/**
	 * @param array $rawResponse API response from processor
	 * @return ApprovePaymentResponse
	 */
	abstract public static function fromRawResponse( array $rawResponse ): ApprovePaymentResponse;
}
