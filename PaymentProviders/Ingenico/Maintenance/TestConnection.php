<?php

namespace SmashPig\PaymentProviders\Ingenico\Maintenance;

use SmashPig\Core\Logging\Logger;

require 'IngenicoMaintenance.php';

$maintClass = 'SmashPig\PaymentProviders\Ingenico\Maintenance\TestConnection';

class TestConnection extends IngenicoMaintenance {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['method']['default'] = 'test';
	}

	/**
	 * Do the actual work of the script.
	 */
	protected function runIngenicoScript() {
		$response = $this->provider->testConnection();
		Logger::info( print_r( $response, true ) );
	}
}

require RUN_MAINTENANCE_IF_MAIN;
