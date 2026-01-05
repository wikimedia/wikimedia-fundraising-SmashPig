<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

class BaseParser {

	protected array $row;

	public function __construct( $row ) {
		$this->row = $row;
	}

	private function getTransactionCodes(): array {
		return [
			'T0002' => 'recurring_payment',
			// In our case preapproved payment is braintree.
			'T0003' => 'preapproved_payment',
			'T0006' => 'subscription_payment',
			// This is in our tests but not really documented - it seems to be blocked
			'T0013' => 'risky_payment',
			'T0200' => 'currency_conversion',
			'T1106' => 'reversal',
			'T0400' => 'withdrawal',
			'T1107' => 'refund',
			'T1201' => 'chargeback',
		];
	}

	protected function getTransactionType(): string {
		return $this->getTransactionCodes()[$this->getTransactionCode()] ?? '';
	}

	protected function getTransactionCode(): string {
		return (string)( $this->row['Transaction Event Code'] ?? '' );
	}

	protected function isBraintreePayment(): bool {
		return $this->getTransactionType() === 'preapproved_payment';
	}

	protected function isRecurringPayment(): bool {
		return $this->getTransactionType() === 'recurring_payment';
	}

	protected function isGravy(): bool {
		$customField = $this->row['Custom Field'] ?? '';
		return strlen( $customField ) > 20 && !str_contains( $customField, '.' ) && !is_numeric( $customField );
	}

	protected function getTransactionPrefix(): string {
		$code = $this->getTransactionCode();
		return $code !== '' ? substr( $code, 0, 3 ) : '';
	}

	/**
	 * Is refund-ish type: 'refund'|'reversal'|'chargeback' .
	 */
	protected function isReversalType(): bool {
		return in_array( $this->getTransactionType(), [ 'reversal', 'refund', 'chargeback' ], true );
	}

	protected function isReversalPrefix(): bool {
		$prefix = $this->getTransactionPrefix();
		return $prefix === 'T11' || $prefix === 'T12';
	}

	protected function isPaymentishPrefix(): bool {
		$prefix = $this->getTransactionPrefix();
		return in_array( $prefix, [ 'T00', 'T03', 'T05', 'T07', 'T22' ], true );
	}

	protected function isDebitPaymentToSomeoneElse(): bool {
		// Only applies to payment-ish events (not refunds/chargebacks)
		if ( !$this->isPaymentishPrefix() ) {
			return false;
		}

		// Recurring payments are handled separately.
		if ( $this->isRecurringPayment() ) {
			return false;
		}

		$drCr = (string)( $this->row['Transaction Debit or Credit'] ?? '' );
		return $drCr === 'DR';
	}

	protected function getGateway(): string {
		if ( $this->row['Payment Source'] === 'Express Checkout' ) {
			return 'paypal_ec';
		}
		# Skating further onto thin ice, we identify recurring version by
		# the first character of the subscr_id
		if ( $this->isRecurringPayment() && str_starts_with( $this->row['PayPal Reference ID'] ?? '', 'I' ) ) {
			return 'paypal_ec';
		}

		if ( $this->isReversalType() ) {
			if ( !empty( $this->row['Invoice ID'] ) ) {
				return 'paypal_ec';
			}
		}
		return 'paypal';
	}

	protected function getOrderID(): string {
		foreach ( [ 'Invoice ID', 'Transaction Subject', 'Custom Field' ] as $field ) {
			$value = trim( (string)( $this->row[$field] ?? '' ) );
			if ( $value === '' ) {
				continue;
			}
			if ( preg_match( '/^[0-9]+(\.[0-9]+)?$/', $value ) === 1 ) {
				return $value;
			}
		}
		return '';
	}

	protected function getContributionTrackingId(): ?int {
		$parts = explode( '.', $this->getOrderID() );
		return $parts[0] ? (int)$parts[0] : null;
	}

	protected function getOriginalFeeAmount(): float {
		$fee = $this->row['Fee Amount'] ?? 0;
		if ( $fee ) {
			return $fee / 100;
		}
		return 0.0;
	}

}
