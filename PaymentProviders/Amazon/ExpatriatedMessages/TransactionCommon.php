<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

/**
 * Field shared by non-subscription notifications.
 */
abstract class TransactionCommon extends AmazonMessage {
	//FIXME: it is not ideal to list the fields twice.
	protected $fields = array(
		'addressFullName',
		'addressLine1',
		'addressLine2',
		'addressState',
		'addressZip',
		'addressCountry',
		'addressPhone',
		'buyerEmail',
		'buyerName',
		'certificateUrl',
		'customerEmail',
		'customerName',
		'dateInstalled',
		'isShippingAddressProvided',
		'operation',
		'notificationType',
		'paymentMethod',
		'paymentReason',
		'recipientEmail',
		'recipientName',
		'referenceId',
		'signature',
		'signatureVersion',
		'signatureMethod',
		'subscriptionId',
		'tokenId',
		'tokenType',
		'transactionAmount',
		'transactionDate',
		'transactionId',
		'transactionStatus',
	);
	protected $addressFullName;
	protected $addressLine1;
	protected $addressLine2;
	protected $addressState;
	protected $addressZip;
	protected $addressCountry;
	protected $addressPhone;
	protected $buyerEmail;
	protected $buyerName;
	protected $certificateUrl;
	protected $customerEmail;
	protected $customerName;
	protected $dateInstalled;
	protected $isShippingAddressProvided;
	protected $operation;
	protected $notificationType;
	protected $paymentMethod;
	protected $paymentReason;
	protected $recipientEmail;
	protected $recipientName;
	protected $referenceId;
	protected $signature;
	protected $signatureVersion;
	protected $signatureMethod;
	protected $subscriptionId;
	protected $tokenId;
	protected $tokenType;
	protected $transactionAmount;
	protected $transactionDate;
	protected $transactionId;
	protected $transactionStatus;

	public function normalizeForQueue() {
		$queueMsg = parent::normalizeForQueue();

		if ( preg_match( "/^([A-Z]{3}) ([0-9.]+)$/", $this->transactionAmount, $matches ) ) {
			$currency = $matches[1];
			$amount = $matches[2];
		} else {
			throw new SmashPigException( "Unhandled transaction amount , {$this->transactionAmount}" );
		}

		$queueMsg->gateway_txn_id = $this->transactionId;
		$queueMsg->last_name = $this->buyerName;
		$queueMsg->email = $this->buyerEmail;
		$queueMsg->street_address = $this->addressLine1;
		$queueMsg->supplemental_address_1 = $this->addressLine2;
		$queueMsg->state_province = $this->addressState;
		$queueMsg->country = $this->addressCountry;
		$queueMsg->postal_code = $this->addressZip;
		$queueMsg->currency = $currency;
		$queueMsg->gross = $amount;
		$queueMsg->date = $this->transactionDate;

		if ( $this->subscriptionId ) {
			$queueMsg->subscr_id = $this->subscriptionId;
			$queueMsg->txn_type = 'subscr_payment';
			$queueMsg->recurring = 1;
		}

		$queueMsg->correlationId = "{$queueMsg->gateway}-{$queueMsg->gateway_txn_id}";

		return $queueMsg;
	}
}
