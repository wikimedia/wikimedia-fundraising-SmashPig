<?php namespace SmashPig\PaymentProviders\GlobalCollect\ExpatriatedMessages;

class PaymentMessage extends GlobalCollectMessage {
	protected $additional_reference;
	protected $amount;
	protected $attempt_id;
	protected $currency_code;
	protected $effort_id;
	protected $merchant_id;
	protected $order_id;
	protected $payment_method_id;
	protected $payment_product_id;
	protected $payment_reference;
	protected $received_date;
	protected $status_date;
	protected $status_id;

	protected $fields = array(
		'additional_reference',
		'amount' => array( 'map' => 'gross' ),
		'attempt_id',
		'currency_code' => array( 'map' => 'currency' ),
		'effort_id',
		'merchant_id',
		'order_id' => array( 'map' => 'gateway_txn_id' ),
		'payment_method_id',
		'payment_product_id' => array( 'map' => 'payment_product' ),
		'payment_reference',
		'received_date' => array( 'map' => 'date' ),
		'status_date',
		'status_id',
	);

	public function getDestinationQueue() {
		// XXX
		return 'donations';
	}
}
