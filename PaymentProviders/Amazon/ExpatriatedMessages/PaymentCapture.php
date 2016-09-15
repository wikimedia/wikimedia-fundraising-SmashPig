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
		$this->completion_message_id = "amazon-$captureReferenceId";
		$this->order_id = $captureReferenceId;

		$parts = explode( '-', $captureReferenceId );
		$this->contribution_tracking_id = $parts[0];

		$this->date = UtcDate::getUtcTimestamp( $details['CreationTimestamp'] );

		$this->currency = $details['CaptureAmount']['CurrencyCode'];
		$this->gross = $details['CaptureAmount']['Amount'];
		$this->fee = $details['CaptureFee']['Amount'];

		$this->gateway_status = $details['CaptureStatus']['State'];
	}

	public function normalizeForQueue() {
		$queueMsg = parent::normalizeForQueue();

		$queueMsg->completion_message_id = $this->completion_message_id;
		$queueMsg->contribution_tracking_id = $this->contribution_tracking_id;
		$queueMsg->currency = $this->currency;
		$queueMsg->date = $this->date;
		$queueMsg->gateway_status = $this->gateway_status;
		$queueMsg->gateway_txn_id = $this->gateway_txn_id;
		$queueMsg->order_id = $this->order_id;
		$queueMsg->payment_method = 'amazon';
		$queueMsg->fee = $this->fee;

		return $queueMsg;
	}
}
