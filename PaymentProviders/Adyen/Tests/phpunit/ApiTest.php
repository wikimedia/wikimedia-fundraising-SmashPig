<?php
// workaround for WMF CI as php-soap is not installed
namespace {
	if ( !extension_loaded( 'soap' ) ) {
		class SoapClient {
		}
	}
}

namespace SmashPig\PaymentProviders\Adyen\Test {
	include 'PaymentProviders/Adyen/WSDL/Payment.php';

	use SmashPig\PaymentProviders\Adyen\Api;
	use PHPUnit\Framework\TestCase;
	use SmashPig\PaymentProviders\Adyen\WSDL\Amount;

	class ApiTest extends TestCase {
		public function testGetAmountFormatsNonFractionalCurrency() {
			// open up access to the private getAmount method
			$reflectionClass = new \ReflectionClass( Api::class );
			$reflectionMethod = $reflectionClass->getMethod( 'getWsdlAmountObject' );
			$reflectionMethod->setAccessible( true );

			// mock the Api class to skip constructor call
			$apiMock = $this->getMockBuilder( Api::class )
				->disableOriginalConstructor()
				->getMock();

			// getAmount params
			$params = [
				'currency' => 'JPY',
				'amount' => '150'
			];

			$expected = new Amount();
			$expected->currency = 'JPY';
			$expected->value = '150';

			// call getAmount via reflection
			$result = $reflectionMethod->invoke( $apiMock, $params );
			$this->assertEquals( $expected, $result );
		}

		public function testGetAmountFormatsFractionalCurrency() {
			// open up access to the private getAmount method
			$reflectionClass = new \ReflectionClass( Api::class );
			$reflectionMethod = $reflectionClass->getMethod( 'getWsdlAmountObject' );
			$reflectionMethod->setAccessible( true );

			// mock the Api class to skip constructor call
			$apiMock = $this->getMockBuilder( Api::class )
				->disableOriginalConstructor()
				->getMock();

			// getAmount params
			$params = [
				'currency' => 'USD',
				'amount' => '9.99'
			];

			$expected = new Amount();
			$expected->currency = 'USD';
			$expected->value = '999';

			// call getAmount via reflection
			$result = $reflectionMethod->invoke( $apiMock, $params );
			$this->assertEquals( $expected, $result );
		}

		public function testGetAmountFormatsExponent3Currency() {
			// open up access to the private getAmount method
			$reflectionClass = new \ReflectionClass( Api::class );
			$reflectionMethod = $reflectionClass->getMethod( 'getWsdlAmountObject' );
			$reflectionMethod->setAccessible( true );

			// mock the Api class to skip constructor call
			$apiMock = $this->getMockBuilder( Api::class )
				->disableOriginalConstructor()
				->getMock();

			// getAmount params
			$params = [
				'currency' => 'IQD',
				'amount' => '74.698'
			];

			$expected = new Amount();
			$expected->currency = 'IQD';
			$expected->value = '74698';

			// call getAmount via reflection
			$result = $reflectionMethod->invoke( $apiMock, $params );
			$this->assertEquals( $expected, $result );
		}
	}
}
