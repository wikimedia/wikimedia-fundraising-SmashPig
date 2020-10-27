<?php

namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;

class CancelPaymentStatus implements StatusNormalizer {

	/**
	 * @param string $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ): string {
		switch ( $adyenStatus ) {
			case '[cancel-received]':
				$status = FinalStatus::CANCELLED;
				break;
			default:
				throw new OutOfBoundsException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
