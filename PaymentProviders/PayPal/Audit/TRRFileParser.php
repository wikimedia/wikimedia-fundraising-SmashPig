<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

use SmashPig\Core\UnhandledException;

class TRRFileParser extends BaseParser {

	/**
	 * @throws UnhandledException
	 */
	public function getMessage(): array {
		if ( $this->row['Transactional Status'] !== 'S' ) {
			// Skip transaction, not settled.
			throw new UnhandledException( 'Transaction status skipped: ' . $this->row['Transactional Status'] );
		}
		if ( $this->isGravy() ) {
			throw new UnhandledException( 'Gravy transaction skipped' );
		}
		if ( $this->isBraintreePayment() ) {
			throw new UnhandledException( 'Braintree transaction skipped' );
		}
		if ( $this->isDebitPaymentToSomeoneElse() ) {
			throw new UnhandledException( 'Debit payment skipped' );
		}
		if ( !empty( $this->row['Billing Address Line1'] ) ) {
			$addr_prefix = 'Billing Address ';
		} else {
			$addr_prefix = 'Shipping Address ';
		}
		// Note that the python script sets no thank you to 'Audit configured not to send messages'
		// I have not retained that as it does not seem like a decision for this low in the stack.
		$msg = [
			'gateway_txn_id' => $this->row['Transaction ID'],
			'gateway' => $this->getGateway(),
			'audit_file_gateway' => $this->getGateway(),
			'date' => strtotime( $this->row['Transaction Initiation Date'] ),
			'settled_date' => strtotime( $this->row['Transaction Completion Date'] ),
			'gross' => ( (float)$this->row['Gross Transaction Amount'] ) / 100,
			'currency' => $this->row['Gross Transaction Currency'],
			'original_fee_amount' => $this->getOriginalFeeAmount(),
			'fee' => $this->getOriginalFeeAmount(),
			'gateway_status' => $this->row['Transactional Status'],
			'note' => $this->row['Transaction Note'],
			'email' => $this->row["Payer's Account ID"],
			'payment_method' => 'paypal',
			'street_address' => $this->row[$addr_prefix . 'Line1'],
			'supplemental_address_1' => $this->row[$addr_prefix . 'Line2'],
			'city' => $this->row[$addr_prefix . 'City'],
			'state_province' => $this->row[$addr_prefix . 'State'],
			'postal_code' => $this->row[$addr_prefix . 'Zip'],
			'country' => $this->row[$addr_prefix . 'Country'],
			'last_name' => $this->row['Last Name'] ?? null,
			'first_name' => $this->row['First Name'] ?? null,
			'payment_submethod' => $this->row['Card Type'] ?? null,
			'order_id' => $this->getOrderID(),
			'contribution_tracking_id' => $this->getContributionTrackingId(),
		];

		if ( $this->isRecurringPayment() ) {
			$msg['txn_type'] = 'subscr_payment';
			$msg['subscr_id'] = $this->row['PayPal Reference ID'];
		}
		if ( $this->isReversalType() ) {
			$msg['type'] = $this->getTransactionType();
			$msg['gateway_refund_id'] = $this->row['Transaction ID'];
			$msg['gross_currency'] = $this->row['Gross Transaction Currency'];

			if ( ( $this->row['PayPal Reference ID Type'] ?? '' ) === 'TXN' ) {
				$msg['gateway_parent_id'] = $this->row['PayPal Reference ID'];
			}
		} elseif ( $this->isReversalPrefix() ) {
			// Prefix says refund/chargeback, but code isn't one we handle -> skip (Python: "-Unknown (Refundish type)")
			throw new UnhandledException( 'Unhandled refundish transaction code: ' . $this->getTransactionCode() );
		}
		return $msg;
	}

}
