<?php
namespace SmashPig\PaymentProviders\Amazon\Tests;

use ReflectionClass;
use SmashPig\Core\Context;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class AmazonTestCase extends BaseSmashPigUnitTestCase {

	protected $mockClient;

	public function setUp() {
		parent::setUp();
		chdir( __DIR__ ); // So the mock client can find its response files
		$config = AmazonTestConfiguration::instance();
		Context::get()->setProviderConfiguration( $config );
		$this->mockClient = $config->object( 'payments-client', true );
		$this->mockClient->calls = array();
		$this->mockClient->returns = array();
		$this->mockClient->exceptions = array();
	}

	public function tearDown() {
		parent::tearDown();
		$api = new ReflectionClass( 'SmashPig\PaymentProviders\Amazon\AmazonApi' );
		$instance = $api->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null );
	}
}
