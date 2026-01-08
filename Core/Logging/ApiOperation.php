<?php

namespace SmashPig\Core\Logging;

/**
 * Canonical API operation names for standardised API timing logs.
 *
 * These enum values ensure consistent naming across different payment processors,
 * making it easier to analyze and compare API performance metrics.
 */
enum ApiOperation: string {
	/**
	 * Create/authorize a payment
	 */
	case AUTHORIZE = 'authorize';

	/**
	 * Capture/approve an authorized payment
	 */
	case CAPTURE = 'capture';

	/**
	 * Refund a payment
	 */
	case REFUND = 'refund';

	/**
	 * Cancel/void a payment
	 */
	case CANCEL = 'cancel';

	/**
	 * Get available payment methods
	 */
	case GET_PAYMENT_METHODS = 'getPaymentMethods';

	/**
	 * Get payment/transaction status
	 */
	case GET_PAYMENT_STATUS = 'getPaymentStatus';

	/**
	 * Get payment details after redirect
	 */
	case GET_PAYMENT_DETAILS = 'getPaymentDetails';

	/**
	 * Create a payment session (e.g., for hosted payment pages)
	 */
	case CREATE_SESSION = 'createSession';

	/**
	 * Delete a stored payment token
	 */
	case DELETE_TOKEN = 'deleteToken';

	/**
	 * Get saved payment details/tokens
	 */
	case GET_SAVED_PAYMENT_DETAILS = 'getSavedPaymentDetails';

	/**
	 * Get refund details
	 */
	case GET_REFUND = 'getRefund';

	/**
	 * Get report execution details
	 */
	case GET_REPORT_EXECUTION = 'getReportExecution';

	/**
	 * Generate report download URL
	 */
	case GET_REPORT_DOWNLOAD_URL = 'getReportDownloadUrl';

	/**
	 * Get payment service definition
	 */
	case GET_PAYMENT_SERVICE_DEFINITION = 'getPaymentServiceDefinition';

	/**
	 * Delete data for GDPR compliance
	 */
	case DELETE_DATA = 'deleteData';

	/**
	 * Maps processor-specific method names to canonical operations.
	 *
	 * @param string $processor The payment processor name (e.g., 'adyen', 'gravy')
	 * @param string $method The processor-specific method name
	 * @return self The canonical operation
	 * @throws \UnexpectedValueException If the processor/method combination is not mapped
	 */
	public static function fromProcessorMethod( string $processor, string $method ): self {
		return match ( [ strtolower( $processor ), $method ] ) {
			// Adyen mappings
			[ 'adyen', 'createPaymentFromEncryptedDetails' ] => self::AUTHORIZE,
			[ 'adyen', 'createPaymentFromToken' ] => self::AUTHORIZE,
			[ 'adyen', 'createBankTransferPaymentFromCheckout' ] => self::AUTHORIZE,
			[ 'adyen', 'createSEPABankTransferPayment' ] => self::AUTHORIZE,
			[ 'adyen', 'createACHDirectDebitPayment' ] => self::AUTHORIZE,
			[ 'adyen', 'createGooglePayPayment' ] => self::AUTHORIZE,
			[ 'adyen', 'createApplePayPayment' ] => self::AUTHORIZE,
			[ 'adyen', 'approvePayment' ] => self::CAPTURE,
			[ 'adyen', 'refundPayment' ] => self::REFUND,
			[ 'adyen', 'cancel' ] => self::CANCEL,
			[ 'adyen', 'cancelAutoRescue' ] => self::CANCEL,
			[ 'adyen', 'getPaymentMethods' ] => self::GET_PAYMENT_METHODS,
			[ 'adyen', 'getPaymentDetails' ] => self::GET_PAYMENT_DETAILS,
			[ 'adyen', 'getSavedPaymentDetails' ] => self::GET_SAVED_PAYMENT_DETAILS,
			[ 'adyen', 'deleteDataForPayment' ] => self::DELETE_DATA,
			[ 'adyen', 'createApplePaySession' ] => self::CREATE_SESSION,

			// Gravy mappings
			[ 'gravy', 'createPayment' ] => self::AUTHORIZE,
			[ 'gravy', 'createPaymentSession' ] => self::CREATE_SESSION,
			[ 'gravy', 'approvePayment' ] => self::CAPTURE,
			[ 'gravy', 'refundTransaction' ] => self::REFUND,
			[ 'gravy', 'cancelTransaction' ] => self::CANCEL,
			[ 'gravy', 'getTransaction' ] => self::GET_PAYMENT_STATUS,
			[ 'gravy', 'deletePaymentToken' ] => self::DELETE_TOKEN,
			[ 'gravy', 'getRefund' ] => self::GET_REFUND,
			[ 'gravy', 'getReportExecutionDetails' ] => self::GET_REPORT_EXECUTION,
			[ 'gravy', 'generateReportDownloadUrl' ] => self::GET_REPORT_DOWNLOAD_URL,
			[ 'gravy', 'getPaymentServiceDefinition' ] => self::GET_PAYMENT_SERVICE_DEFINITION,

			default => throw new \UnexpectedValueException(
				"Unknown payment operation for processor '$processor' and method '$method'"
			),
		};
	}
}
