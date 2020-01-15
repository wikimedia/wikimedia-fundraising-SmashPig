<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Adyen\AdyenPaymentsAPI;

$maintClass = '\SmashPig\Maintenance\TestAdyenRecurring';

/**
 * Test out a recurring Adyen transaction
 */
class TestAdyenRecurring extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'token', 'recurring payment token', false );
		$this->addOption( 'currency', 'recurring payment currency', false );
		$this->addOption( 'amount', 'recurring payment amount', false );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$config = Context::get()->getProviderConfiguration();
		$account = array_keys( $config->val( "accounts" ) )[0]; // this feels fragile
		/** @var AdyenPaymentsAPI $api */
		$api = $config->object( 'api' );
		$api->setAccount( $account );

		// it feels like we should tell createPayment this a recurring authorise call in the event that
		// we add in the option to make non-recurring authorise calls in the future.
		$params['recurring'] = true;
		// this is a required field for Adyen createPayment calls so we put something testy in here for now
		$params['reference'] = "RECURRING-TEST-".rand( 0, 10000 );

		$params['token'] = $this->getOption( 'token' );
		$params['currency'] = $this->getOption( 'currency' );
		$params['amount'] = $this->getOption( 'amount' );

		// this is the Authorise call
		$createPaymentResponse = $api->createPayment( $params );
		if ( $createPaymentResponse ) {
			Logger::info( "Recurring Payment Authorised: " . $createPaymentResponse );
		} else {
			Logger::info( "Recurring Payment Attempt Failed: " );
		}

		// this is the Capture call
		$approvePaymentResponse = $api->approvePayment( $createPaymentResponse, $params );
		if ( $approvePaymentResponse ) {
				Logger::info( "Recurring Payment Captured: " . $approvePaymentResponse );
		} else {
				Logger::info( "Recurring Payment Attempt Failed: " );
		}

		// need to push to the donations queue?
	}
}

require RUN_MAINTENANCE_IF_MAIN;
