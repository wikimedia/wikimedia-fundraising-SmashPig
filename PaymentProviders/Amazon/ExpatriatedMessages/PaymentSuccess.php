<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

class PaymentSuccess extends TransactionCommon {
	public function getDestinationQueue() {
		return 'verified';
	}
}
