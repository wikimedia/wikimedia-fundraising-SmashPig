<?php

namespace SmashPig\PaymentProviders\Chariot;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class Deposit {
	private array $deposit;

	public function __construct( array $deposit ) {
		$this->deposit = $deposit;
	}

	/**
	 * Get a value from the deposit array at the given path.
	 *
	 * This function also enforces us updating our metadata as to which
	 * fields are used, helping us to in-code document.
	 *
	 * @param string $path
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	private function getValue( string $path, mixed $default = null ): mixed {
		ChariotObjectMetadata::assertDepositFieldIsUsed( $path );

		$value = $this->deposit;
		foreach ( explode( '.', $path ) as $key ) {
			if ( !is_array( $value ) || !array_key_exists( $key, $value ) ) {
				return $default;
			}
			$value = $value[$key];
		}

		return $value;
	}

	public function getDeposit(): array {
		return $this->deposit;
	}

	public function getId(): string {
		$id = trim( (string)( $this->getValue( 'id', '' ) ) );
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

	public function getCreatedAt(): string {
		return (string)( $this->deposit['created_at'] ?? '' );
	}

	public function getUpdatedAt(): string {
		return (string)( $this->deposit['updated_at'] ?? '' );
	}

	public function getSettledAt(): string {
		return (string)( $this->deposit['settled_at'] ?? '' );
	}

	public function getPaymentSourceId(): string {
		return (string)( $this->deposit['payment_source_id'] ?? '' );
	}

	public function getCheckNumber(): string {
		return (string)( $this->getValue( 'transfer.check_deposit.auxiliary_on_us' ) );
	}

	public function getSettledAmount(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getSettledAmountInMinorUnits(),
			$this->getCurrency()
		);
	}

	public function getZeroAmountRounded(): string {
		return CurrencyRoundingHelper::round( 0.0, $this->getCurrency() );
	}

	public function getSettledAmountInMinorUnits(): int {
		return (int)( $this->deposit['transfer']['amount'] ?? 0 );
	}

	/**
	 * Get a deposit timestamp for filenames.
	 *
	 * @return string
	 */
	public function getDepositTimestampForFilename(): string {
		$candidates = [
			$this->getSettledAt(),
			$this->getCreatedAt(),
			$this->getUpdatedAt(),
		];

		foreach ( $candidates as $candidate ) {
			if ( !is_string( $candidate ) || trim( $candidate ) === '' ) {
				continue;
			}
			$timestamp = strtotime( $candidate );
			if ( $timestamp !== false ) {
				return gmdate( 'YmdHis', $timestamp );
			}
		}

		return gmdate( 'YmdHis' );
	}

}
