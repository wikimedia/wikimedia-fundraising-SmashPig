<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class DelayedTokenStatus implements StatusNormalizer {

	/**
	 * @param string $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ): string {
		switch ( $adyenStatus ) {
			case 'Authorised':
			case 'Received':
			case 'RedirectShopper':
				$status = FinalStatus::PENDING;
				break;
			case 'Refused':
			case 'Error':
				$status = FinalStatus::FAILED;
				break;
			default:
				throw new UnexpectedValueException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
