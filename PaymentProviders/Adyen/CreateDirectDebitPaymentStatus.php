<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use OutOfBoundsException;

class CreateDirectDebitPaymentStatus extends FinalStatus {

	/**
	 * @param $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( $adyenStatus ) {
		switch ( $adyenStatus ) {
			case 'Received':
				$status = static::COMPLETE;
				break;
			case 'Refused':
				$status = static::FAILED;
				break;
			default:
				throw new OutOfBoundsException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
