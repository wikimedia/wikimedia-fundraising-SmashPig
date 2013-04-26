<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

class SubscriptionCanceled extends AmazonMessage {
	protected $fields = array(
		'status',
		'statusReason',
		'subscriptionId',
	);
	protected $status;
	protected $statusReason;
	protected $subscriptionId;
}
