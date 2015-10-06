<?php

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Amazon\AmazonApi;

class ApiTest extends BaseSmashPigUnitTestCase {

	protected $mockClient;

	public function setUp() {
		parent::setUp();
		$config = new Configuration(
			__DIR__ . '/../../../../config_defaults.php',
			__DIR__ . '/../config_test.php',
			'amazon',
			true
		);
		Context::init( $config );
		$this->mockClient = Context::get()->getConfiguration()->obj( 'payments-client', true );
		$this->mockClient->calls = array();
		$this->mockClient->returns = array();
		$this->mockClient->exceptions = array();
	}

	public function testFindParent() {
		$this->mockClient->returns['getAuthorizationDetails'][] = 'Declined';
		$this->mockClient->returns['getAuthorizationDetails'][] = 'Closed';
		$parentId = AmazonApi::findRefundParentId( 'P01-0133129-0199515-R019658' );
		$this->assertEquals( 'P01-0133129-0199515-C019658', $parentId, 'Did not get the right refund parent ID' );
	}
}
