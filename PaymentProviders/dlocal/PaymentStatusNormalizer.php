<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class PaymentStatusNormalizer implements StatusNormalizer {

	/**
	 * @var array
	 */
	public const SUCCESS_STATUS = [ FinalStatus::COMPLETE ];

	/**
	 * @param string $paymentProcessorStatus
	 * @return bool
	 */
	public function isSuccessStatus( string $paymentProcessorStatus ): bool {
		return in_array( $this->normalizeStatus( $paymentProcessorStatus ), static::SUCCESS_STATUS, true );
	}

	/**
	 * @param string $paymentProcessorStatus
	 * @return string
	 */
	public function normalizeStatus( string $paymentProcessorStatus ): string {
		switch ( $paymentProcessorStatus ) {
			case 'PENDING':
				$normalizedStatus = FinalStatus::PENDING_POKE;
				break;
			case 'PAID':
				$normalizedStatus = FinalStatus::COMPLETE;
				break;
			default:
				throw new UnexpectedValueException( "Unknown status $paymentProcessorStatus" );
		}

		return $normalizedStatus;
	}
}
