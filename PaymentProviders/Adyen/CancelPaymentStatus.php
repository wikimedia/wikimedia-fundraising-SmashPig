<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use OutOfBoundsException;

class CancelPaymentStatus implements StatusNormalizer {

	/**
	 * @param $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ) : string {
		switch ( $adyenStatus ) {
			case '[cancel-received]':
				$status = FinalStatus::COMPLETE;
				break;
			default:
				throw new OutOfBoundsException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
