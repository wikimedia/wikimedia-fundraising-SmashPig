<?php

namespace SmashPig\Tests\Logging;

use PHPUnit\Framework\TestCase;
use SmashPig\Core\Logging\ApiOperation;

/**
 * @group Timings
 */
class ApiOperationTest extends TestCase {

	public function testEnumCasesHaveCorrectValues(): void {
		$this->assertSame( 'authorize', ApiOperation::AUTHORIZE->value );
		$this->assertSame( 'capture', ApiOperation::CAPTURE->value );
		$this->assertSame( 'refund', ApiOperation::REFUND->value );
		$this->assertSame( 'cancel', ApiOperation::CANCEL->value );
		$this->assertSame( 'getPaymentMethods', ApiOperation::GET_PAYMENT_METHODS->value );
		$this->assertSame( 'getPaymentStatus', ApiOperation::GET_PAYMENT_STATUS->value );
		$this->assertSame( 'getPaymentDetails', ApiOperation::GET_PAYMENT_DETAILS->value );
		$this->assertSame( 'createSession', ApiOperation::CREATE_SESSION->value );
		$this->assertSame( 'deleteToken', ApiOperation::DELETE_TOKEN->value );
		$this->assertSame( 'getSavedPaymentDetails', ApiOperation::GET_SAVED_PAYMENT_DETAILS->value );
		$this->assertSame( 'getRefund', ApiOperation::GET_REFUND->value );
		$this->assertSame( 'getReportExecution', ApiOperation::GET_REPORT_EXECUTION->value );
		$this->assertSame( 'getReportDownloadUrl', ApiOperation::GET_REPORT_DOWNLOAD_URL->value );
		$this->assertSame( 'getPaymentServiceDefinition', ApiOperation::GET_PAYMENT_SERVICE_DEFINITION->value );
		$this->assertSame( 'deleteData', ApiOperation::DELETE_DATA->value );
	}

	public function testFromProcessorMethodMapsAdyenMethods(): void {
		$this->assertSame(
			ApiOperation::AUTHORIZE,
			ApiOperation::fromProcessorMethod( 'adyen', 'createPaymentFromEncryptedDetails' )
		);
		$this->assertSame(
			ApiOperation::AUTHORIZE,
			ApiOperation::fromProcessorMethod( 'adyen', 'createPaymentFromToken' )
		);
		$this->assertSame(
			ApiOperation::AUTHORIZE,
			ApiOperation::fromProcessorMethod( 'adyen', 'createGooglePayPayment' )
		);
		$this->assertSame(
			ApiOperation::CAPTURE,
			ApiOperation::fromProcessorMethod( 'adyen', 'approvePayment' )
		);
		$this->assertSame(
			ApiOperation::REFUND,
			ApiOperation::fromProcessorMethod( 'adyen', 'refundPayment' )
		);
		$this->assertSame(
			ApiOperation::CANCEL,
			ApiOperation::fromProcessorMethod( 'adyen', 'cancel' )
		);
		$this->assertSame(
			ApiOperation::GET_PAYMENT_METHODS,
			ApiOperation::fromProcessorMethod( 'adyen', 'getPaymentMethods' )
		);
		$this->assertSame(
			ApiOperation::CREATE_SESSION,
			ApiOperation::fromProcessorMethod( 'adyen', 'createApplePaySession' )
		);
	}

	public function testFromProcessorMethodMapsGravyMethods(): void {
		$this->assertSame(
			ApiOperation::AUTHORIZE,
			ApiOperation::fromProcessorMethod( 'gravy', 'createPayment' )
		);
		$this->assertSame(
			ApiOperation::CREATE_SESSION,
			ApiOperation::fromProcessorMethod( 'gravy', 'createPaymentSession' )
		);
		$this->assertSame(
			ApiOperation::CAPTURE,
			ApiOperation::fromProcessorMethod( 'gravy', 'approvePayment' )
		);
		$this->assertSame(
			ApiOperation::REFUND,
			ApiOperation::fromProcessorMethod( 'gravy', 'refundTransaction' )
		);
		$this->assertSame(
			ApiOperation::CANCEL,
			ApiOperation::fromProcessorMethod( 'gravy', 'cancelTransaction' )
		);
		$this->assertSame(
			ApiOperation::GET_PAYMENT_STATUS,
			ApiOperation::fromProcessorMethod( 'gravy', 'getTransaction' )
		);
		$this->assertSame(
			ApiOperation::DELETE_TOKEN,
			ApiOperation::fromProcessorMethod( 'gravy', 'deletePaymentToken' )
		);
	}

	public function testFromProcessorMethodIsCaseInsensitiveForProcessor(): void {
		$this->assertSame(
			ApiOperation::AUTHORIZE,
			ApiOperation::fromProcessorMethod( 'ADYEN', 'createPaymentFromEncryptedDetails' )
		);
		$this->assertSame(
			ApiOperation::AUTHORIZE,
			ApiOperation::fromProcessorMethod( 'Gravy', 'createPayment' )
		);
		$this->assertSame(
			ApiOperation::CAPTURE,
			ApiOperation::fromProcessorMethod( 'AdYeN', 'approvePayment' )
		);
	}

	public function testFromProcessorMethodThrowsForUnknownProcessor(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( "Unknown payment operation for processor 'unknown' and method 'someMethod'" );

		ApiOperation::fromProcessorMethod( 'unknown', 'someMethod' );
	}

	public function testFromProcessorMethodThrowsForUnknownMethod(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( "Unknown payment operation for processor 'adyen' and method 'unknownMethod'" );

		ApiOperation::fromProcessorMethod( 'adyen', 'unknownMethod' );
	}
}
