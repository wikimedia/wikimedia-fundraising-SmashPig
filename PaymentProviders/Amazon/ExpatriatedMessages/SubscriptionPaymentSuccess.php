<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

class SubscriptionPaymentSuccess extends TransactionCommon {
	public function getDestinationQueue() {
		return 'verified';
	}
}
