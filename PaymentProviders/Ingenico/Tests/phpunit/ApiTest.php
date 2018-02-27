<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use DateTime;
use SmashPig\PaymentProviders\Ingenico\Api;
use SmashPig\PaymentProviders\Ingenico\Authenticator;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class ApiTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Authenticator
	 */
	protected $authenticator;

	/**
	 * @var Api
	 */
	protected $api;

	public function setUp() {
		parent::setUp();
		$providerConfiguration = $this->setProviderConfiguration( 'ingenico' );
		$this->curlWrapper = $this->createMock( '\SmashPig\Core\Http\CurlWrapper' );
		$providerConfiguration->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );

		$this->authenticator = new Authenticator(
			'5e45c937b9db33ae',
			'I42Zf4pVnRdroHfuHnRiJjJ2B6+22h0yQt/R3nZR8Xg='
		);
		$this->api = new Api(
			'https://example.com',
			'9876'
		);
	}

	public function testCreateRequest() {
		$headerVerification = function ( $headers ) {
			$date = new DateTime( $headers['Date'] );
			return $date !== null &&
				$headers['Content-Type'] === 'application/json';
		};

		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'https://example.com/v1/9876/testPath' ),
				$this->equalTo( 'POST' ),
				$this->callback( $headerVerification ),
				$this->equalTo( '{"foo":"bar"}' )
			)->willReturn( [
				'body' => '{"baz":"quux"}'
			] );

		$this->api->makeApiCall( 'testPath', 'POST', [ 'foo' => 'bar' ] );
	}

	/**
	 * @expectedException SmashPig\PaymentProviders\Ingenico\ApiException
	 * @expectedExceptionMessage Ingenico error id 460d9c9c-098c-4d84-b1e5-ee27ec601757. Error code 9002: MISSING_OR_INVALID_AUTHORIZATION
	 */
	public function testError() {
		$this->curlWrapper->method( 'execute' )
			->willReturn( [
				'body' => '{"errorId" : "460d9c9c-098c-4d84-b1e5-ee27ec601757","errors" : [ {   "code" : "9002",   "message" : "MISSING_OR_INVALID_AUTHORIZATION",   "httpStatusCode" : 403} ] }',
				'headers' => [],
				'status' => 403
			] );
		$this->api->makeApiCall( 'testPath', 'POST', [ 'foo' => 'bar' ] );
	}
}
