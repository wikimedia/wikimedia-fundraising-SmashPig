<?php

namespace SmashPig\PaymentProviders\CheckoutCom\Audit;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class SettlementBreakdownReport extends CheckoutComAudit {

	protected array $row;

	public function __construct( array $row ) {
		$this->row = $row;
	}

	/**
	 * @param array<string,string|null> $row
	 * @return array<string,mixed>
	 */
	protected function parseDonation( array $row ): array {
		$msg = $this->setCommonValues( $row );
		$msg['type'] = 'donation';
		$msg['currency'] = $msg['original_currency'] = $row['Processing Currency'];
		$msg['settled_currency'] = $row['Holding Currency'];
		$msg['original_total_amount'] = $msg['gross'] = $this->amount( $row['Gross In Processing Currency'], $row['Processing Currency'] );
		$msg['original_fee_amount'] = $this->amount( (float)$row['Deduction In Holding Currency'] / $msg['exchange_rate'], $row['Processing Currency'] );
		$msg['original_net_amount'] = $this->amount( (float)$msg['original_total_amount'] + (float)$msg['original_fee_amount'], $row['Processing Currency'] );
		$msg['settled_fee_amount'] = $this->amount( $row['Deduction In Holding Currency'], $row['Holding Currency'] );
		$msg['settled_net_amount'] = $this->amount( $row['Net In Holding Currency'], $row['Holding Currency'] );
		$msg['settled_total_amount'] = $this->amount( $row['Gross In Holding Currency'], $row['Holding Currency'] );

		return $msg;
	}

	/**
	 * @param array<string,string|null> $row
	 * @param string $type
	 * @return array<string,mixed>
	 */
	protected function parseRefund( array $row, string $type ): array {
		$msg = $this->setCommonValues( $row );
		$msg['type'] = $type === 'chargeback' ? 'chargeback' : 'refund';
		$msg['gross'] = abs( $this->amount( $row['Gross In Processing Currency'], $row['Processing Currency'] ) );
		$msg['gross_currency'] = $msg['original_currency'] = $row['Processing Currency'];
		$msg['backend_processor_parent_id'] = $row['Payment ID'];
		$msg['backend_processor_reversal_id'] = $row['Payment ID'];
		$msg['original_total_amount'] = -abs( $this->amount( $row['Gross In Processing Currency'], $row['Processing Currency'] ) );
		$msg['original_fee_amount'] = $this->amount( (float)$row['Deduction In Holding Currency'] / $msg['exchange_rate'], $row['Processing Currency'] );
		$msg['original_net_amount'] = $this->amount( ( (float)$msg['original_total_amount'] + (float)$msg['original_fee_amount'] ), $row['Processing Currency'] );
		$msg['settled_fee_amount'] = $this->amount( $row['Deduction In Holding Currency'], $row['Holding Currency'] );
		$msg['settled_net_amount'] = $this->amount( $row['Net In Holding Currency'], $row['Holding Currency'] );
		$msg['settled_total_amount'] = $this->amount( $row['Gross In Holding Currency'], $row['Holding Currency'] );
		$msg['settled_currency'] = $row['Holding Currency'];

		return $msg;
	}

	/**
	 * @param array<string,string|null> $row
	 * @return array<string,mixed>
	 */
	protected function getFeeTransaction( array $row ): array {
		$amount = $this->amount( $row['Net In Holding Currency'], $row['Holding Currency'] );

		return [
			'gateway' => 'checkoutcom',
			'audit_file_gateway' => 'checkoutcom',
			'type' => 'fee',
			// Payment ID may be the same as for a relevant donation so make it more unique.
			'gateway_txn_id' => 'fee-' . $row['Payment ID'] . strtotime( ( (string)$row['Processed On'] ) ) . $row['Type'],
			'gateway_account' => $row['Processing Channel Name'],
			'settlement_batch_reference' => $row['Payout ID'],
			'date' => $this->getUtcTimestamp( $row['Processed On'] ),
			'settled_date' => $this->getUtcTimestamp( $row['Available On'] ),
			'settled_currency' => $row['Holding Currency'],
			'settled_total_amount' => '0.00',
			'settled_fee_amount' => $amount,
			'settled_net_amount' => $amount,
		];
	}

	/**
	 * @param array<string,string|null> $row
	 * @return array<string,mixed>
	 */
	protected function getPayoutTransaction( array $row ): array {
		return [
			'gateway' => 'checkoutcom',
			'audit_file_gateway' => 'checkoutcom',
			'type' => 'payout',
			'gateway_txn_id' => $row['Payout ID'],
			'gateway_account' => $row['Processing Channel Name'],
			'settlement_batch_reference' => $row['Payout ID'],
			'date' => $this->getUtcTimestamp( $row['Processed On'] ),
			'settled_date' => $this->getUtcTimestamp( $row['Available On'] ),
			'settled_currency' => $row['Holding Currency'],
			'settled_total_amount' => $this->amount( $row['Net In Holding Currency'], $row['Holding Currency'] ),
		];
	}

	/**
	 * @param array<string,string|null> $row
	 * @return array<string,mixed>
	 */
	protected function setCommonValues( array $row ): array {
		[ $paymentMethod, $paymentSubmethod ] = $this->decodePaymentMethod( $row['Payment Method'] ?? '' );

		return [
			'gateway' => 'gravy',
			'audit_file_gateway' => 'checkoutcom',
			'backend_processor' => 'checkoutcom',
			'gateway_account' => $row['Processing Channel Name'],
			'gateway_txn_id' => $this->getGatewayTxnId( $row['Reference'] ),
			'backend_processor_txn_id' => $row['Payment ID'],
			'auth_id' => $row['Payment ID'],
			'payment_orchestrator_reconciliation_id' => $row['Reference'],
			'settlement_batch_reference' => $row['Payout ID'],
			'payment_method' => $paymentMethod,
			'payment_submethod' => $paymentSubmethod,
			'date' => $this->getUtcTimestamp( $row['Processed On'] ),
			'settled_date' => $this->getUtcTimestamp( $row['Available On'] ),
			'exchange_rate' => (float)( $row['FX Rate Applied'] ?: 1 ),
		];
	}

	/**
	 * Gravy gives us the payment_orchestrator_reconciliation_id in the report.
	 * Audit wants gateway_txn_id in the same base62 form used by contribution_extra.gateway_txn_id.
	 */
	protected function getGatewayTxnId( ?string $orchestratorReconciliationId ): ?string {
		if ( $orchestratorReconciliationId === null || $orchestratorReconciliationId === '' ) {
			return null;
		}

		return Base62Helper::toUuid( $orchestratorReconciliationId );
	}

	protected function getUtcTimestamp( ?string $date ): ?int {
		if ( $date === null || $date === '' ) {
			return null;
		}
		return ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
	}

	protected function amount( ?string $value, $currency ): string {
		return CurrencyRoundingHelper::round( (float)$value, $currency );
	}

	/**
	 * @return array{0:string,1:string|null}
	 */
	protected function decodePaymentMethod( ?string $paymentMethod ): array {
		switch ( strtolower( $paymentMethod ?? '' ) ) {
			case 'visa':
				return [ 'cc', 'visa' ];
			case 'mastercard':
				return [ 'cc', 'mc' ];
			case 'amex':
				return [ 'cc', 'amex' ];
			case 'discover':
				return [ 'cc', 'discover' ];
			default:
				return [ 'cc', null ];
		}
	}
}
