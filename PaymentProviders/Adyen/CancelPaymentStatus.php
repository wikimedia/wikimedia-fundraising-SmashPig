<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use OutOfBoundsException;

class CancelPaymentStatus extends FinalStatus {

	/**
	 * @param $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( $adyenStatus ) {
		switch ( $adyenStatus ) {
			case '[cancel-received]':
				$status = static::COMPLETE;
				break;
			default:
				throw new OutOfBoundsException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
