<?php

namespace SmashPig\PaymentProviders\Chariot;

class Deposit {
	private array $deposit;

	public function __construct( array $deposit ) {
		$this->deposit = $deposit;
	}

	public function getId(): string {
		$id = trim( (string)( $this->deposit['id'] ?? '' ) );
		if ( $id === '' ) {
			throw new \RuntimeException( 'Deposit payload missing id' );
		}
		return $id;
	}

	public function getSettlementBatchReference(): string {
		return preg_replace( '/^deposit_/', '', $this->getId() ) ?: $this->getId();
	}

	public function getCurrency(): string {
		return (string)( $this->deposit['transfer']['currency'] ?? '' );
	}

	public function getPaymentMethod(): string {
		$transfer = $this->deposit['transfer'];
		$paymentMethod = empty( $transfer['check_deposit'] ) ? 'EFT' : 'Check';
		if ( !empty( $transfer['inbound_ach_transfer'] ) ) {
			$paymentMethod = 'ACH';
		}
		return $paymentMethod;
	}

}
