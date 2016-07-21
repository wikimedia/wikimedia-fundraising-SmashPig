<?php namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;

/**
 * Message sent to the 'cc-limbo' queue when a payment has been initiated and sent off to the gateway.
 */
class DonationInterfaceMessage extends KeyedOpaqueStorableObject {
	public $captured = '';
	public $city = '';
	public $city_2 = '';
	public $comment = '';
	public $contribution_tracking_id = '';
	public $country = '';
	public $country_2 = '';
	public $currency = '';
	public $date = '';
	public $email = '';
	public $fee = '';
	public $first_name = '';
	public $first_name_2 = '';
	public $gateway = '';
	public $gateway_account = '';
	public $gateway_txn_id = '';
	public $gross = '';
	public $language = '';
	public $last_name = '';
	public $last_name_2 = '';
	public $middle_name = '';
	public $net = '';
	public $order_id = '';
	public $payment_method = '';
	public $payment_submethod = '';
	public $postal_code = '';
	public $postal_code_2 = '';
	public $premium_language = '';
	public $recurring = '';
	public $response = '';
	public $risk_score = '';
	public $size = '';
	public $state_province = '';
	public $state_province_2 = '';
	public $street_address = '';
	public $street_address_2 = '';
	public $supplemental_address_1 = '';
	public $supplemental_address_2 = '';
	public $user_ip = '';
	public $utm_campaign = '';
	public $utm_medium = '';
	public $utm_source = '';
}
