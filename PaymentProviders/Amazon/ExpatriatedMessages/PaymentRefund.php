<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

use SmashPig\Core\UtcDate;

/**
 * Handle Amazon refund notifications
 * https://payments.amazon.com/documentation/apireference/201752720#201752740
 */
abstract class PaymentRefund extends AmazonMessage {

	protected $gateway_parent_id;

	public function __construct( $values ) {
		parent::__construct();
		$details = $values['RefundDetails'];

		$this->setGatewayIds( $details['AmazonRefundId'] );

		$this->date = UtcDate::getUtcTimestamp( $details['CreationTimestamp'] );

		$this->currency = $details['RefundAmount']['CurrencyCode'];
		$this->gross = $details['RefundAmount']['Amount'];
		// TODO: do we need to use FeeRefunded for anything?

		// Note: When we programmatically refund anything, we'll have to set
		// the seller refund reference ID to the Amazon ID of the capture we're
		// refunding, to avoid a torturous dance of many API calls to find it
		$this->gateway_parent_id = $details['RefundReferenceId'];
	}

	public function normalizeForQueue() {
		$queueMsg = parent::normalizeForQueue();

		$queueMsg->gateway_parent_id = $this->gateway_parent_id;
		$queueMsg->gateway_refund_id = $this->gateway_txn_id;

		$queueMsg->gross_currency = $this->currency;

		// Docs say RefundType is always 'SellerInitiated'
		// Waiting to hear back about how they inform us of chargebacks.
		$queueMsg->type = 'refund';

		return $queueMsg;
	}
}
