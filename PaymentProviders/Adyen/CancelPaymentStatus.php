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
