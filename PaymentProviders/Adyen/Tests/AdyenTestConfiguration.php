<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Core\GlobalConfiguration;
use SmashPig\Tests\TestingProviderConfiguration;

class AdyenTestConfiguration extends TestingProviderConfiguration {

	public static function instance( $overrides = array(), GlobalConfiguration $globalConfig ) {
		$config = static::createForProvider( 'adyen', $globalConfig );
		$config->override( $overrides );

		return $config;
	}

	public static function createWithSuccessfulApi( GlobalConfiguration $globalConfig ) {
		$override = array( 'api' =>
			array(
				'class' => 'SmashPig\PaymentProviders\Adyen\Tests\MockAdyenPaymentsAPI',
				'constructor-parameters' =>
					array( 'Success!' )
			)
		);
		return self::instance( $override, $globalConfig );
	}

	public static function createWithUnsuccessfulApi( GlobalConfiguration $globalConfig ) {
		$override = array( 'api' =>
			array(
				'class' => 'SmashPig\PaymentProviders\Adyen\Tests\MockAdyenPaymentsAPI',
				'constructor-parameters' =>
					// FIXME: Really?  or boolean `false` as it would be if
					// we parsed "false" from yaml?
					array( 'false' )
			)
		);
		return self::instance( $override, $globalConfig );
	}
}
