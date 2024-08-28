<?php
namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class GravyRefundResponseFactory extends GravyPaymentResponseFactory {
	protected static function createBasicResponse(): RefundPaymentResponse {
		return new RefundPaymentResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !$paymentResponse instanceof RefundPaymentResponse ) {
			return;
		}
		self::setRefundReason( $paymentResponse, $normalizedResponse );
		self::setRefundAmount( $paymentResponse, $normalizedResponse );
		self::setRefundCurrency( $paymentResponse, $normalizedResponse );
		self::setRefundId( $paymentResponse, $normalizedResponse );
		self::setParentId( $paymentResponse, $normalizedResponse );
	}

	protected static function setRefundReason( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		# Gravy sends us two IPNs per refund, a 'processing' and 'succeeded' IPN.
		# Currently, they send over the reason property for the first but not the latter, so for now, we handle it here
		# until they can fix the bug on their side.
		$normalizedResponse['reason'] = $normalizedResponse['reason'] ?? 'Reason not provided';

		$refundResponse->setReason( $normalizedResponse['reason'] );
	}

	protected static function setRefundAmount( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setAmount( $normalizedResponse['amount'] );
	}

	protected static function setRefundCurrency( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setCurrency( $normalizedResponse['currency'] );
	}

	protected static function setRefundId( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setGatewayRefundId( $normalizedResponse['gateway_refund_id'] );
	}

	protected static function setParentId( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setGatewayParentId( $normalizedResponse['gateway_parent_id'] );
	}

}
