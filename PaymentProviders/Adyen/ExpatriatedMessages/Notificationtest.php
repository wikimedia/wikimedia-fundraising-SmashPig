<?php namespace SmashPig\PaymentProviders\Adyen\Messages;

use SmashPig\Core\Logging\Logger;

class Notificationtest extends AdyenMessage {
	/**
	 * Determine if the message is complete, well formed, and able to be
	 * processed. Returning true will continue processing of this message.
	 * Returning false will halt processing of the message but will not be
	 * treated as an error. Throw an exception if a critical error has
	 * occurred.
	 *
	 * @return bool True if the message was complete and can be processed
	 */
	public function validate() {
		// TODO: Implement validate() method.
	}

	public function runActionChain() {
		// TODO: Implement runActionChain() method.
	}
}
