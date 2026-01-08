<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\UnhandledException;

class BaseParser {

	protected array $row;
	protected array $headers;
	protected array $conversionRows;

	public function __construct( array $row, array $headers, array $conversionRows ) {
		$this->row = $row;
		$this->headers = $headers;
		$this->conversionRows = $conversionRows;
	}

	/**
	 * @return int
	 */
	public function hasConversion(): bool {
		return isset( $this->conversionRows[$this->row['Invoice ID']] );
	}

	/**
	 * @see https://developer.paypal.com/docs/reports/reference/tcodes
	 * @return string[]
	 */
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
		if ( ( $this->row['Payment Source'] ?? '' ) === 'Express Checkout' ) {
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

	protected function getFeeAmount(): float {
		$fee = $this->row['Fee Amount'] ?? 0;
		if ( $fee ) {
			return $fee / 100;
		}
		return 0.0;
	}

	protected function getOriginalFeeAmount(): float {
		$fee = $this->getFeeAmount();
		if ( $this->row['Fee Debit or Credit'] === 'DR' ) {
			return -$fee;
		}
		return $fee;
	}

	/**
	 * @return float
	 */
	protected function getOriginalNetAmount(): string {
		return (string)( $this->getOriginalTotalAmount() + $this->getOriginalFeeAmount() );
	}

	/**
	 * @return float
	 */
	protected function getOriginalTotalAmount(): string {
		$totalAmount = (float)( $this->row['Gross Transaction Amount'] ) / 100;
		if ( $this->row['Transaction Debit or Credit'] === 'DR' ) {
			$totalAmount = -$totalAmount;
		}
		return (string)$totalAmount;
	}

	/**
	 * @return float|int
	 */
	protected function getExchangeRate(): int|float {
		$exchangeRate = 1;
		if ( $this->hasConversion() ) {
			$conversion = $this->conversionRows[$this->row['Invoice ID']];
			$originalCurrency = $conversion[0];
			$convertedCurrency = $conversion[1];
			$exchangeRate = $convertedCurrency['Gross Transaction Amount'] / $originalCurrency['Gross Transaction Amount'];
		}
		return $exchangeRate;
	}

	/**
	 * @return mixed
	 */
	protected function getSettledCurrency(): mixed {
		if ( $this->hasConversion() ) {
			return $this->conversionRows[$this->row['Invoice ID']][1]['Gross Transaction Currency'];
		}
		return $this->row['Gross Transaction Currency'];
	}

	protected function getSettledTotalAmount(): string {
		if ( !$this->hasConversion() ) {
			return (string)$this->getOriginalTotalAmount();
		}
		return CurrencyRoundingHelper::round( $this->getOriginalTotalAmount() * $this->getExchangeRate(), $this->getSettledCurrency() );
	}

	protected function getSettledNetAmount(): string {
		if ( !$this->hasConversion() ) {
			return (string)( (float)$this->getSettledTotalAmount() + (float)$this->getSettledFeeAmount() );
		}
		return (string)( $this->conversionRows[$this->row['Invoice ID']][1]['Gross Transaction Amount'] / 100 );
	}

	protected function getSettledFeeAmount(): string {
		if ( !$this->hasConversion() ) {
			return (string)$this->getOriginalFeeAmount();
		}
		// Rely on the conversion being done in getTotalAmount for rounding consistency.
		return (string)( $this->getSettledNetAmount() - $this->getSettledTotalAmount() );
	}

	/**
	 * @return array
	 */
	protected function getGravyFields(): array {
		$gravyFields = [];
		if ( $this->isGravy() ) {
			$gravyFields['backend_processor_txn_id'] = $this->row['Transaction ID'];
			$gravyFields['backend_processor'] = $this->getGateway();
			$gravyFields['payment_orchestrator_reconciliation_id'] = $this->row['Custom Field'];
		}
		return $gravyFields;
	}

	/**
	 * @return array
	 */
	protected function getRecurringFields(): array {
		$recurringFields = [];
		if ( $this->isRecurringPayment() ) {
			$recurringFields['txn_type'] = 'subscr_payment';
			$recurringFields['subscr_id'] = $this->row['PayPal Reference ID'];
		}
		return $recurringFields;
	}

	/**
	 * @return array
	 * @throws \SmashPig\Core\UnhandledException
	 */
	protected function getReversalFields(): array {
		$reversalFields = [];
		if ( $this->isReversalType() ) {
			$reversalFields['type'] = $this->getTransactionType();
			$reversalFields['gateway_refund_id'] = $this->row['Transaction ID'];
			$reversalFields['gross_currency'] = $this->row['Gross Transaction Currency'];

			if ( ( $this->row['PayPal Reference ID Type'] ?? '' ) === 'TXN' ) {
				$reversalFields['gateway_parent_id'] = $this->row['PayPal Reference ID'];
			}
		} elseif ( $this->isReversalPrefix() ) {
			// Prefix says refund/chargeback, but code isn't one we handle -> skip (Python: "-Unknown (Refundish type)")
			throw new UnhandledException( 'Unhandled refundish transaction code: ' . $this->getTransactionCode() );
		}
		return $reversalFields;
	}

}
