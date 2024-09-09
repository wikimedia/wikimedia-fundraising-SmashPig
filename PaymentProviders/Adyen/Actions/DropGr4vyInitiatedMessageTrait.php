<?php

namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

trait DropGr4vyInitiatedMessageTrait {
	/**
	 * checks if a message is initiated by Gr4vy
	 *
	 * @param ListenerMessage $msg
	 * @param string $type
	 * @return bool
	 */
	public function isGr4vyInitiatedMessage( $msg, $type = 'authorisation' ) {
		if ( isset( $msg->additionalData['metadata.gr4vy_intent'] ) ) {
			Logger::info(
				"Got {$type} ipn message, initiated by gravy for with psp reference {$msg->pspReference}. Doing nothing just drop."
			);
			return true;
		}
		return false;
	}
}
