<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use OutOfBoundsException;
use SmashPig\PaymentData\StatusNormalizer;

class ApprovePaymentStatus implements StatusNormalizer {

	/**
	 * @param $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ): string {
		switch ( $adyenStatus ) {
			case '[capture-received]':
				$status = FinalStatus::COMPLETE;
				break;
			default:
				throw new OutOfBoundsException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
