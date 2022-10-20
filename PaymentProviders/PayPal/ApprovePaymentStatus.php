<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class ApprovePaymentStatus implements StatusNormalizer {

	/**
	 * TODO: why is this not a static method?
	 *
	 * @param string $paypalStatus
	 * @return string
	 */
	public function normalizeStatus( string $paypalStatus ): string {
		switch ( $paypalStatus ) {
			case 'Completed':
				$status = FinalStatus::COMPLETE;
				break;
			default:
				throw new UnexpectedValueException( "Unknown PayPal status $paypalStatus" );
		}

		return $status;
	}
}
