<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

class SubscriptionCompleted extends AmazonMessage {
	protected $fields = array(
		'status',
		'subscriptionId',
	);
	protected $status;
	protected $subscriptionId;
}
