<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class BankTransferPaymentProvider extends HostedPaymentProvider {

	/**
	 * Should be set to one of the two SUBSCRIPTION_FREQUENCY_UNIT_* values
	 * 'ONDEMAND' has fewer limitations for prenotify compared with 'MONTH'
	 * (allows sending a retry in the same month, since needs 2 days to process).
	 * HostedPaymentApiRequestMapper uses ONDEMAND by default if not set.
	 * @var string|null
	 */
	protected $upiSubscriptionFrequency;

	/**
	 * @var int|null
	 */
	protected $upiSubscriptionDurationInMonths;

	public function __construct( ?array $params = null ) {
		parent::__construct();
		if ( !empty( $params['upi_subscription_frequency'] ) ) {
			$this->upiSubscriptionFrequency = $params['upi_subscription_frequency'];
		}
		if ( !empty( $params['upi_subscription_months'] ) ) {
			$this->upiSubscriptionDurationInMonths = $params['upi_subscription_months'];
		}
	}

	public static function isRecurringUpi( array $params ): bool {
		return ( $params['recurring'] ?? false ) &&
			( $params['payment_submethod'] ?? '' ) === 'upi';
	}

	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws ApiException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		if ( self::isRecurringUpi( $params ) ) {
			$params['upi_subscription_frequency'] = $this->upiSubscriptionFrequency;
			$params['upi_subscription_months'] = $this->upiSubscriptionDurationInMonths;
		}
		return parent::createPayment( $params );
	}
}
