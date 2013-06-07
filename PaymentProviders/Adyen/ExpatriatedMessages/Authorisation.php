<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Adyen\Actions\PaymentCaptureAction;
use SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem;

class Authorisation extends AdyenMessage {

	/** @var string The payment method used in this transaction, eg visa, mc, ideal, ev, wallie, etc... */
	public $paymentMethod = '';

	/** @var array Modification operations currently supported by the referenced transaction. This includes
	 * things like CAPTURE, REFUND, and CANCEL. */
	public $operations = array();

	/** @var string When success is set to true and the payment method is visa, mc, or amex this field contains
	 * the authorization code, last 4 digits of the card, and the expiry date as <6 digit auth>:<Last 4>:<MM/YYYY>.
	 * When success is false, this is a string describing the refusal reason. */
	public $reason = '';

	/**
	 * Overloads the generic Adyen method adding fields specific to the Authorization message
	 * type.
	 *
	 * @param \SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem $obj
	 */
	protected function constructFromWSDL( NotificationRequestItem $msgObj ) {
		parent::constructFromWSDL( $msgObj );

		$this->paymentMethod = $msgObj->paymentMethod;

		if ( $msgObj->operations ) {
			$this->operations = (array)$msgObj->operations->string;
		} else {
			$this->operations = array();
		}

		$this->reason = $msgObj->reason;

		// Recreate the correlation ID because we have more information about things
		$this->correlationId = static::createCorrelationId( $this->merchantReference );
	}

	/**
	 * Will run all the actions that are loaded (from the 'actions' configuration
	 * node) and that are applicable to this message type. Will return true
	 * if all actions returned true. Otherwise will return false. This implicitly
	 * means that the message will be re-queued if any action fails. Therefore
	 * all actions need to be idempotent.
	 *
	 * @returns bool True if all actions were successful. False otherwise.
	 */
	public function runActionChain() {
		$action = new PaymentCaptureAction();
		$result = $action->execute( $this );

		if ( $result === true ) {
			return parent::runActionChain();
		} else {
			return false;
		}
	}
}
