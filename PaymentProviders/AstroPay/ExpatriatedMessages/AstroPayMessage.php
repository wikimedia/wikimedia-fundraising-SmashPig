<?php namespace SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\AstroPay\Messages\NormalizedMessage;

abstract class AstroPayMessage extends ListenerMessage {

	protected $fields = array(
		'result',
		'x_invoice',
		'x_iduser',
		'x_description',
		'x_document',
		'x_bank',
		'x_payment_type',
		'x_bank_name',
		'x_amount',
		'x_control',
		'x_currency',
	);

	protected $result;
	protected $x_invoice;
	protected $x_iduser;
	protected $x_description;
	protected $x_document;
	protected $x_bank;
	protected $x_payment_type;
	protected $x_bank_name;
	protected $x_amount;
	protected $x_control;
	protected $x_currency;

	public function validate() {
		return true;
	}

	public function getSignedString() {
		return $this->result . $this->x_amount . $this->x_invoice;
	}

	public function getSignature() {
		return $this->x_control;
	}

	public function constructFromValues( array $values ) {
		foreach ( $this->fields as $key ) {
			$this->$key = ( array_key_exists( $key, $values ) ? $values[$key] : '' );
		}
		// Need to set the correlationId during construction
		// or inflight message store will get confused
		$this->correlationId = "astropay-{$this->x_document}";
	}

	abstract function getDestinationQueue();

	/**
	 * Map AstroPay's fields to ours
	 *
	 * @return \SmashPig\PaymentProviders\AstroPay\Messages\NormalizedMessage associative queue message thing
	 */
	public function normalizeForQueue() {
		$queueMsg = new NormalizedMessage();

		$queueMsg->gateway = 'astropay';

		// AstroPay invoice format is ct_id.numAttempt
		$invoiceParts = explode( '.', $this->x_invoice );
		$queueMsg->contribution_tracking_id = $invoiceParts[0];

		$queueMsg->gateway_txn_id = $this->x_document;
		$queueMsg->currency = $this->x_currency;
		$queueMsg->gross = $this->x_amount;
		$queueMsg->date = time();
		$queueMsg->gateway_status = $this->result;
		$queueMsg->correlationId = $this->correlationId;

		// This message has no donor info.  Add a key to indicate that there is
		// a message in the pending queue with the rest of the info we need.
		// This differs from the correlationId because we don't get the gateway
		// transaction ID unless the donor makes it back to the thank you page.
		$queueMsg->completion_message_id = 'astropay-' . $this->x_invoice;

		return $queueMsg;
	}
}
