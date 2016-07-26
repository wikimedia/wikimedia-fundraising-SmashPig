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
					array( 'inst-args' =>
						array( $success ? 'Success!' : 'false' )
					)
				)
			)
		);
		$config->override( $override );

		// Create sqlite schema
		$sql = file_get_contents( __DIR__ . '/../../../Schema/sqlite/001_CreatePendingTable.sqlite.sql' );
		$db = PendingDatabase::get();
		if ( $db ) {
			$db->getDatabase()->exec( $sql );
		}

		return $config;
	}
}
