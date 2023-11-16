<?php

namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\JsonSerializableObject;
use SmashPig\PaymentProviders\Adyen\Actions\PaymentCaptureAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

class AuthorisationActionTest extends BaseAdyenTestCase {
 private $jobsAdyenQueue;

	public function setUp() : void {
		parent::setUp();
		$this->jobsAdyenQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-adyen' );
	}

	public function testAutoRescueMessageIsInstanceOfAuthorisation(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' )
		);

		$this->assertInstanceOf( Authorisation::class, $authorisation );
	}

	public function testAutoRescueIsRecurringInstallmentReturnsCorrectValue(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' )
		);
		$this->assertTrue( $authorisation->isSuccessfulAutoRescue() );
	}

	public function testSuccessfulAutoRescueAuth(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$capture = JsonSerializableObject::fromJsonProxy( $msg['php-message-class'], json_encode( $msg ) );

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => 10,
				'currency' => 'USD',
				'gateway_txn_id' => $authorisation->pspReference
			] )
			->willReturn( AdyenTestConfiguration::getSuccessfulApproveResult() );

		$capture->execute();
		$this->assertEquals( $msg['merchantReference'], $authorisation->merchantReference );
		$this->assertEquals( $msg['shopperReference'], $authorisation->shopperReference );
	}

	public function testEndedAutoRescueAuth(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/ended_auto_rescue_auth.json' )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();
		$this->assertTrue( $msg['isEndedAutoRescue'] );
		$this->assertEquals( $msg['merchantReference'], $authorisation->merchantReference );
		$this->assertEquals( $msg['pspReference'], $authorisation->pspReference );

		$capture = JsonSerializableObject::fromJsonProxy( $msg['php-message-class'], json_encode( $msg ) );
		$this->mockApi->expects( $this->once() )
			->method( 'cancel' )
			->with( $msg['pspReference'] )
			->willReturn( AdyenTestConfiguration::getSuccessfulCancelResult() );

		$successfulCancelResult = $capture->execute();
		$this->assertTrue( $successfulCancelResult );
	}
}
