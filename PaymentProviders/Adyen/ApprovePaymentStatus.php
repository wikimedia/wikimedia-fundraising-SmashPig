<?php

namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;

class ApprovePaymentStatus implements StatusNormalizer {

	/**
	 * @param string $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ): string {
		switch ( $adyenStatus ) {
			case '[capture-received]':
			case 'received':
				$status = FinalStatus::COMPLETE;
				break;
			default:
				throw new OutOfBoundsException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
