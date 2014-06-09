<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

class SubscriptionSuccessful extends AmazonMessage {
	//FIXME: it is not ideal to list the fields twice.
	protected $fields = array(
		'addressLine1',
		'addressLine2',
		'addressName',
		'buyerEmail',
		'buyerName',
		'city',
		'country',
		'noOfPromotionTransactions',
		'paymentMethod',
		'paymentReason',
		'phoneNumber',
		'promotionAmount',
		'recipientEmail',
		'recipientName',
		'recurringFrequency',
		'referenceId',
		'signature',
		'startValidityDate',
		'state',
		'status',
		'subscriptionId',
		'subscriptionPeriod',
		'transactionAmount',
		'transactionSerialNumber',
		'zip',
	);
	protected $addressLine1;
	protected $addressLine2;
	protected $addressName;
	protected $buyerEmail;
	protected $buyerName;
	protected $city;
	protected $country;
	protected $noOfPromotionTransactions;
	protected $paymentMethod;
	protected $paymentReason;
	protected $phoneNumber;
	protected $promotionAmount;
	protected $recipientEmail;
	protected $recipientName;
	protected $recurringFrequency;
	protected $referenceId;
	protected $signature;
	protected $startValidityDate;
	protected $state;
	protected $status;
	protected $subscriptionId;
	protected $subscriptionPeriod;
	protected $transactionAmount;
	protected $transactionSerialNumber;
	protected $zip;

	public function getDestinationQueue() {
		return 'verified';
	}

	public function normalizeForQueue() {
		$queueMsg = parent::normalizeForQueue();

		if ( strtolower($this->recurringFrequency) === '1 month' ) {
			$frequency_unit = "month";
			$frequency_interval = 1;
		} else {
			throw new SmashPigException( "Unhandled subscription interval, {$this->recurringFrequency}" );
		}

		if ( !$this->subscriptionPeriod or strtolower( $this->subscriptionPeriod ) === "forever" ) {
			$installments = 0;
		} else {
			if ( preg_match( "/^(\d+) months?$/i", $this->subscriptionPeriod, $matches ) ) {
				$installments = $matches[1];
			} else {
				throw new SmashPigException( "Unhandled subscription period, {$this->subscriptionPeriod}" );
			}
		}

		if ( preg_match( "/^([A-Z]{3}) ([0-9.]+)$/", $this->transactionAmount, $matches ) ) {
			$currency = $matches[1];
			$amount = $matches[2];
		} else {
			throw new SmashPigException( "Unhandled transaction amount , {$this->transactionAmount}" );
		}

		$queueMsg->txn_type = 'subscr_signup';
		$queueMsg->last_name = $this->buyerName;
		$queueMsg->email = $this->buyerEmail;
		$queueMsg->street_address = $this->addressLine1;
		$queueMsg->supplemental_address_1 = $this->addressLine2;
		$queueMsg->city = $this->city;
		$queueMsg->state_province = $this->state;
		$queueMsg->country = $this->country;
		$queueMsg->postal_code = $this->zip;
		$queueMsg->currency = $currency;
		$queueMsg->gross = $amount;
		$queueMsg->frequency_unit = $frequency_unit;
		$queueMsg->frequency_interval = $frequency_interval;
		$queueMsg->installments = $installments;
		$queueMsg->date = $this->startValidityDate;
		$queueMsg->subscr_id = $this->subscriptionId;
		$queueMsg->recurring = 1;
		$queueMsg->gateway_status = $this->status;
		$queueMsg->contribution_tracking_id = $this->referenceId;

		$queueMsg->correlationId = "{$queueMsg->gateway}-{$queueMsg->subscr_id}";

		return $queueMsg;
	}
}
