<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\PendingDatabase;

class AdyenTestConfiguration extends Configuration {

	public static function instance() {
		return self::createForViewWithOverrideFile(
			'adyen',
			__DIR__ . '/config_test.yaml'
		);
	}

	// TODO: rename
	public static function get( $success ) {
		$config = self::instance();
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
