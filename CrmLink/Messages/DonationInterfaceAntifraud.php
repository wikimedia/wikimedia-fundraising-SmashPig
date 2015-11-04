<?php namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;

/**
 * Message encapsulating fraud scores and outcome
 */
class DonationInterfaceAntifraud extends KeyedOpaqueStorableObject {
	public $contribution_tracking_id = '';
	public $date = '';
	public $gateway = '';
	public $gateway_txn_id = '';
	public $order_id = '';
	public $payment_method = '';
	public $risk_score = '';
	public $score_breakdown = array();
	public $server = '';
	public $user_ip = '';
	public $validation_action = '';
}
