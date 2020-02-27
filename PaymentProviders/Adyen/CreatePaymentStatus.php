<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use OutOfBoundsException;

class CreatePaymentStatus extends FinalStatus {

	/**
	 * @param $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( $adyenStatus ) {
		switch ( $adyenStatus ) {
			case 'Authorised':
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
