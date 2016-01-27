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

	public static function factory(
		DonationInterfaceMessage $message,
		$riskScore,
		$scoreBreakdown = array(),
		$validationAction = 'process' ) {

		$obj = new DonationInterfaceAntifraud();
		$obj->risk_score = $riskScore;
		$obj->score_breakdown = $scoreBreakdown;
		$obj->validation_action = $validationAction;

		$obj->contribution_tracking_id = $message->contribution_tracking_id;
		$obj->date = $message->date;
		$obj->gateway = $message->gateway;
		$obj->gateway_txn_id = $message->gateway_txn_id;
		$obj->order_id = $message->order_id;
		$obj->payment_method = $message->payment_method;
		// no 'server' available
		$obj->user_ip = $message->user_ip;

		return $obj;
	}
}
