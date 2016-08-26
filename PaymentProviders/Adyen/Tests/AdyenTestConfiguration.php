<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\PendingDatabase;

class AdyenTestConfiguration extends Configuration {

	public static function instance( $overrides = array() ) {
		$config = self::createForViewWithOverrideFile(
			'adyen',
			__DIR__ . '/config_test.yaml'
		);
		$config->override( $overrides );

		// FIXME: What is this doing here?
		PendingDatabase::get()->createTable();

		return $config;
	}

	public static function createWithSuccessfulApi() {
		$override = array( 'payment-provider' =>
			array( 'adyen' =>
				array( 'api' =>
					array( 'constructor-parameters' =>
						array( 'Success!' )
					)
				)
			)
		);
		return self::instance( $override );
	}

	public static function createWithUnsuccessfulApi() {
		$override = array( 'payment-provider' =>
			array( 'adyen' =>
				array( 'api' =>
					array( 'constructor-parameters' =>
						// FIXME: Really?  or boolean `false` as it would be if
						// we parsed "false" from yaml?
						array( 'false' )
					)
				)
			)
		);
		return self::instance( $override );
	}
}
