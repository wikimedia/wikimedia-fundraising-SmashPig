<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use OutOfBoundsException;
use SmashPig\PaymentData\StatusNormalizer;

class CreateDirectDebitPaymentStatus  implements StatusNormalizer {

	/**
	 * @param $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ): string {
		switch ( $adyenStatus ) {
			case 'Received':
				$status = FinalStatus::COMPLETE;
				break;
			case 'Refused':
				$status = FinalStatus::FAILED;
				break;
			default:
				throw new OutOfBoundsException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
