<?php namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;

/**
 * Message sent to the 'cc-limbo' queue when a payment has been initiated and sent off to the gateway.
 */
class LimboMessage extends KeyedOpaqueStorableObject {
	public $contribution_tracking_id = '';
	public $comment = '';
	public $size = '';
	public $premium_language = '';
	public $utm_source = '';
	public $utm_medium = '';
	public $utm_campaign = '';
	public $language = '';
	public $referrer = '';
	public $email = '';
	public $first_name = '';
	public $middle_name = '';
	public $last_name = '';
	public $street_address = '';
	public $supplemental_address_1 = '';
	public $city = '';
	public $state_province = '';
	public $country = '';
	public $postal_code = '';
	public $first_name_2 = '';
	public $last_name_2 = '';
	public $street_address_2 = '';
	public $supplemental_address_2 = '';
	public $city_2 = '';
	public $state_province_2 = '';
	public $country_2 = '';
	public $postal_code_2 = '';
	public $gateway = '';
	public $gateway_account = '';
	public $gateway_txn_id = '';
	public $payment_method = '';
	public $payment_submethod = '';
	public $response = '';
	public $currency = '';
	public $original_currency = '';
	public $original_gross = '';
	public $fee = '';
	public $gross = '';
	public $net = '';
	public $user_ip = '';
	public $date = '';
	public $recurring = '';
}
