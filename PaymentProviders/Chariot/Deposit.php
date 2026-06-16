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
		$typeMap = [
			'inbound_ach_transfer' => 'ACH',
			'check_deposit' => 'Check',
			'inbound_account_transfer' => 'EFT',
		];
		return $typeMap[$transfer['type']];
	}

}
