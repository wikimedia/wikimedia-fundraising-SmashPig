<?php namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;

/**
 * Message sent to the 'cc-limbo' queue when a payment has been initiated and sent off to the gateway.
 */
class DonationInterfaceMessage extends KeyedOpaqueStorableObject {
	public $captured = '';
	public $city = '';
	public $contribution_tracking_id = '';
	public $country = '';
	public $currency = '';
	public $date = '';
	public $email = '';
	public $fee = '';
	public $first_name = '';
	public $gateway = '';
	public $gateway_account = '';
	public $gateway_txn_id = '';
	public $gross = '';
	public $language = '';
	public $last_name = '';
	public $middle_name = '';
	public $net = '';
	public $order_id = '';
	public $payment_method = '';
	public $payment_submethod = '';
	public $postal_code = '';
	public $recurring = '';
	public $response = '';
	public $risk_score = '';
	public $state_province = '';
	public $street_address = '';
	public $supplemental_address_1 = '';
	public $user_ip = '';
	public $utm_campaign = '';
	public $utm_medium = '';
	public $utm_source = '';

	public static function fromValues( $values = array() ) {
		$message = new DonationInterfaceMessage();
		foreach ( $values as $key => $value ) {
			if( property_exists( 'DonationInterfaceMessage', $key ) ) {
				$message->$key = $value;
			}
		}
		$message->correlationId = "{$message->gateway}-{$message->order_id}";
	}
}
