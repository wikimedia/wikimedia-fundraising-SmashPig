<?php

namespace SmashPig\PaymentProviders\Adyen\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Maintenance\MaintenanceBase;

$maintClass = 'SmashPig\PaymentProviders\Adyen\Maintenance\TestAdyenConnectivity';

class TestAdyenConnectivity extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'adyen';
	}

	public function execute() {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$api = $providerConfiguration->object( 'api' );
		$result = $api->getPaymentMethods( [
			'country' => 'US',
			'currency' => 'USD',
			'amount' => '1.23',
			'language' => 'en'
		] );
		print_r( $result );
	}
}

require RUN_MAINTENANCE_IF_MAIN;
