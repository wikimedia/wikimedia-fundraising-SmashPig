<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Tests\TestingProviderConfiguration;

class AdyenTestConfiguration extends TestingProviderConfiguration {

	public static function instance( $overrides = array() ) {
		$config = static::createForProvider( 'adyen' );
		$config->override( $overrides );

		return $config;
	}

	public static function createWithSuccessfulApi() {
		$override = array( 'payment-provider' =>
			array( 'adyen' =>
				array( 'api' =>
					array(
						'class' => 'SmashPig\PaymentProviders\Adyen\Tests\MockAdyenPaymentsAPI',
						'constructor-parameters' =>
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
					array(
						'class' => 'SmashPig\PaymentProviders\Adyen\Tests\MockAdyenPaymentsAPI',
						'constructor-parameters' =>
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
