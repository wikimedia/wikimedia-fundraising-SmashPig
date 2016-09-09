<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\PendingDatabase;

class AdyenTestConfiguration extends Configuration {

	public function __construct() {
		parent::__construct(
			'adyen',
			__DIR__ . '/config_test.yaml'
		);
	}

	public static function get( $success ) {
		$config = new AdyenTestConfiguration();
		$override = array( 'payment-provider' =>
			array( 'adyen' =>
				array( 'api' =>
					array( 'constructor-parameters' =>
						array( $success ? 'Success!' : 'false' )
					)
				)
			)
		);
		$config->override( $override );

		PendingDatabase::get()->createTable();

		return $config;
	}
}
