<?php namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;

/**
 * Message sent once frontend donation processing is completed
 */
class DonationInterfaceFinalStatus extends KeyedOpaqueStorableObject {
	public $amount = '';
	public $contribution_tracking_id = '';
	public $country = '';
	public $currency_code = '';
	public $date = '';
	public $gateway = '';
	public $gateway_txn_id = '';
	public $order_id = '';
	public $payment_method = '';
	public $payments_final_status = '';
	public $payment_submethod = '';
	public $server = '';
	public $validation_action = '';
}
