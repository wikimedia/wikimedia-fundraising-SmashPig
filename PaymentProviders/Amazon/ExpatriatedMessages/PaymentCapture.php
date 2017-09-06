<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

use SmashPig\Core\UtcDate;

abstract class PaymentCapture extends AmazonMessage {

	// The completion message ID associates the details in this message with
	// a message in the pending database that has donor and tracking details
	// TODO: either change this to a boolean flag, or act like the Adyen
	// RecordCaptureJob and combine the pending info before sending to Civi
	protected $completion_message_id;
	protected $order_id;
	protected $contribution_tracking_id;
	protected $fee;
	protected $gateway_status;

	public function __construct( $values ) {
		parent::__construct();
		$details = $values['CaptureDetails'];

		$this->setGatewayIds( $details['AmazonCaptureId'] );

		$captureReferenceId = $details['CaptureReferenceId'];

		$this->setOrderId( $captureReferenceId );

		$this->date = UtcDate::getUtcTimestamp( $details['CreationTimestamp'] );

		$this->currency = $details['CaptureAmount']['CurrencyCode'];
		$this->gross = $details['CaptureAmount']['Amount'];
		$this->fee = $details['CaptureFee']['Amount'];

		$this->gateway_status = $details['CaptureStatus']['State'];
	}

	/**
	 * Add fields specific to donation messages
	 *
	 * @return array
	 */
	public function normalizeForQueue() {
		$queueMsg = parent::normalizeForQueue();

		$queueMsg = array_merge( $queueMsg, [
			'completion_message_id' => $this->completion_message_id,
			'contribution_tracking_id' => $this->contribution_tracking_id,
			'currency' => $this->currency,
			'date' => $this->date,
			'gateway_status' => $this->gateway_status,
			'gateway_txn_id' => $this->gateway_txn_id,
			'order_id' => $this->order_id,
			'payment_method' => 'amazon',
			'fee' => $this->fee
		] );

		return $queueMsg;
	}

	/**
	 * Set fields derived from the order ID
	 *
	 * @param string $orderId
	 */
	public function setOrderId( $orderId ) {
		$this->order_id = $orderId;
		$this->completion_message_id = "amazon-$orderId";

		$parts = explode( '-', $orderId );
		$this->contribution_tracking_id = $parts[0];
	}

	/**
	 * @return string
	 */
	public function getOrderId() {
		return $this->order_id;
	}
}
