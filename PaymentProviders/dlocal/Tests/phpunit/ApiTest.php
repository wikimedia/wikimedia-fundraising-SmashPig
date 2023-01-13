<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\Core\Http\CurlWrapper;
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
			'trans-key' => 'test_dg$3434534E',
			'secret' => 'test_ITSASECRET',
			'version' => '2.1',
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
			->willReturn( [
				'status' => 200,
				'body' => '{"result":"test"}',
			] );

		// headers are generated during the call to makeApiCall
		$this->api->makeApiCall( $emptyParams = [] );
	}

	public function testApiCallSetsRequiredFormatDateHeader(): void {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(), // url
				$this->anything(), // method
				$this->callback( function ( $headers ) {
					$this->assertArrayHasKey( 'X-Date', $headers );

					// confirm X-Date string matches format set in docs https://docs.dlocal.com/reference/payins-security#headers
					// e.g. 2023-01-16T14:35:56.990Z
					$expectedDateFormat = 'Y-m-d\TH:i:s.v\Z';
					$dateFromString = \DateTime::createFromFormat( $expectedDateFormat, $headers['X-Date'] );
					$this->assertNotFalse( $dateFromString ); // returns false when string doesn't match format
					$this->assertEquals( $dateFromString->format( $expectedDateFormat ),  $headers['X-Date'] );
					return true; // if we get here, the date header was good were set.
				} )
			)
			->willReturn( [
				'status' => 200,
				'body' => '{"result":"test"}',
			] );

		// headers are generated during the call to makeApiCall
		$this->api->makeApiCall( $emptyParams = [] );
	}

	public function testApiCallGeneratesCorrectHMACSignature(): void {
		$emptyParams = [];

		// curlWrapper::execute() is called within Api::makeApiCall()
		// via OutboundRequest::execute(). I did consider mocking
		// OutboundRequest, but it looks like we typically mock the
		// CurlWrapper for this scenario, which is one level lower.
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(), // url
				$this->anything(), // method
				$this->callback( function ( $headers ) use ( $emptyParams ) {
					// generate the signature here using the expected inputs
					$secret = 'test_ITSASECRET';
					$signatureInput = 'test_login' . $headers['X-Date'] . json_encode( $emptyParams );
					$calculatedSignature = hash_hmac( 'sha256', $signatureInput, $secret );
					// dLocal signatures have a text prefix which needs to be in the header
					$signatureTextPrefix = 'V2-HMAC-SHA256, Signature: ';
					$expectedSignatureValue = $signatureTextPrefix . $calculatedSignature;

					// compare generated signature with the signature in the headers
					$this->assertEquals( $expectedSignatureValue, $headers['Authorization'] );
					return true; // if we get here, the headers were set.
				} )
			)
			->willReturn( [
				'status' => 200,
				'body' => '{"result":"test"}',
			] );

		// headers are generated during the call to makeApiCall
		$this->api->makeApiCall( $emptyParams );
	}

	/**
	 * @see PaymentProviders/dlocal/Tests/Data/payment-methods.response
	 */
	public function testGetPaymentMethods(): void {
		$mockResponse = $this->prepareMockResponse( 'payment-methods.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments-methods?country=MX' ),
				$this->equalTo( 'GET' )
			)->willReturn( $mockResponse );

		$results = $this->api->getPaymentMethods( 'MX' ); // MX is Mexico

		$expectedPaymentMethod = [
			'id' => 'OX',
			'type' => 'TICKET',
			'name' => 'Oxxo',
			'logo' => 'https://pay.dlocal.com/views/2.0/images/payments/OX.png',
			'allowed_flows' =>
				[
					0 => 'REDIRECT',
				],
		];

		// the first result for Mexico should be Oxxo
		$this->assertEquals( $expectedPaymentMethod, $results[0] );
	}

	/**
	 * This helper method is an alternative to Tests/BaseSmashPigUnitTestCase.php:setUpResponse(),
	 * which returns the mock response instead of setting it, inside the method.
	 *
	 * The header size counting code is a bit confusing, I forget what it does every time I see it, but
	 * all that it's doing is telling CurlWrapper::parseResponse() where the headers end and the body begins.
	 *
	 * @param string $filename
	 * @param int $statusCode
	 * @return array
	 */
	private function prepareMockResponse( string $filename, int $statusCode ): array {
		$filePath = __DIR__ . '/../Data/' . $filename;
		$fileContents = file_get_contents( $filePath );

		// the +2 here is to include the two line ending chars "\n\n" in the header count. see doc-bloc for more.
		$header_size = strpos( $fileContents, "\n\n" ) + 2;

		return CurlWrapper::parseResponse(
			$fileContents,
			[
				'http_code' => $statusCode,
				'header_size' => $header_size,
			]
		);
	}
}
