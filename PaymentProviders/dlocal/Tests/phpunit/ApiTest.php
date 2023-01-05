<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class ApiTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Api
	 */
	public $api;

	public function setUp(): void {
		parent::setUp();
		$testingProviderConfiguration = $this->setProviderConfiguration( 'dlocal' );
		$testingProviderConfiguration->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );

		$this->api = new Api( [
			'endpoint' => 'http://example.com',
			'login' => 'test_login',
			'trans_key' => 'test_dg$3434534E',
			'secret' => 'test_ITSASECRET',
			'version' => '2.1'
		] );
	}

	/**
	 * TODO: This test should be moved and the visibility of Api::makeApiCall()
	 * changed to protected once we implement the first PaymentProvider action
	 * which internally call Api::makeApiCall().
	 *
	 * For now, this test confirms the behaviour of the code available.
	 *
	 * @return void
	 */
	public function testApiCallSetsRequiredRequestHeaders(): void {
		// curlWrapper::execute() is called within Api::makeApiCall()
		// via OutboundRequest::execute(). I did consider mocking
		// OutboundRequest, but it looks like we typically mock the
		// CurlWrapper for this scenario, which is one level lower.
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(), // url
				$this->anything(), // method
				$this->callback( function ( $headers ) {
					$this->assertArrayHasKey( 'X-Date', $headers );
					$this->assertArrayHasKey( 'X-Login', $headers );
					$this->assertArrayHasKey( 'X-Trans-Key', $headers );
					$this->assertArrayHasKey( 'Content-Type', $headers );
					$this->assertArrayHasKey( 'X-Version', $headers );
					$this->assertArrayHasKey( 'User-Agent', $headers );
					$this->assertArrayHasKey( 'Authorization', $headers );
					return true; // if we get here, the headers were set.
				} )
			)
			->willReturn( $emptyResult = [] );

		// headers are generated during the call to makeApiCall
		$this->api->makeApiCall( $emptyParams = [] );
	}

}
