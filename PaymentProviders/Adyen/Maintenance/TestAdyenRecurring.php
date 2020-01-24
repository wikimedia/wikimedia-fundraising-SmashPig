<?php

namespace SmashPig\PaymentProviders\Adyen\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Adyen\PaymentProvider;

$maintClass = 'SmashPig\PaymentProviders\Adyen\Maintenance\TestAdyenRecurring';

/**
 * Test out a recurring Adyen transaction
 */
class TestAdyenRecurring extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'token', 'recurring payment token', false );
		$this->addOption( 'currency', 'recurring payment currency', false );
		$this->addOption( 'amount', 'recurring payment amount', false );

		$this->desiredOptions['config-node']['default'] = 'adyen';
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$adyen = new PaymentProvider();

		// it feels like we should tell createPayment this a recurring authorise call in the event that
		// we add in the option to make non-recurring authorise calls in the future.
		$params['recurring'] = true;
		// this is a required field for Adyen createPayment calls so we put something testy in here for now
		$params['reference'] = "RECURRING-TEST-" . rand( 0, 10000 );

		$params['token'] = $this->getOption( 'token' );
		$params['currency'] = $this->getOption( 'currency' );
		$params['amount'] = $this->getOption( 'amount' );

		// this is the Authorise call
		$createPaymentResponse = $adyen->createPayment( $params );
		if ( $createPaymentResponse->isSuccessful() ) {
			Logger::info( "Recurring Payment Authorised: " . json_encode( $createPaymentResponse->getRawResponse() ) );
		} else {
			Logger::info( "Recurring Payment Attempt Failed: " . json_encode( $createPaymentResponse->getRawResponse() ) );
		}

		// this is the Capture call
		$params['payment_id'] = $createPaymentResponse->getGatewayTrxnId();
		$approvePaymentResponse = $adyen->approvePayment( $params );
		if ( $approvePaymentResponse->isSuccessful() ) {
			Logger::info( "Recurring Payment Captured: " . json_encode( $approvePaymentResponse->getRawResponse() ) );
		} else {
			Logger::info( "Recurring Payment Attempt Failed: " . json_encode( $approvePaymentResponse->getRawResponse() ) );
		}
	}
}

require RUN_MAINTENANCE_IF_MAIN;
