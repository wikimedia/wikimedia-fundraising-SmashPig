<?php

namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\JsonSerializableObject;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Adyen\Actions\PaymentCaptureAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

class AutoRescueActionTest extends BaseAdyenTestCase {
 private $jobsAdyenQueue;

	public function setUp() : void {
		parent::setUp();
		$this->jobsAdyenQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-adyen' );
		$this->recurringQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/recurring' );
	}

	public function testAutoRescueMessageIsInstanceOfAuthorisation(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Autorescue',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' )
		);

		$this->assertInstanceOf( Authorisation::class, $authorisation );
	}

	public function testAutoRescueIsRecurringInstallmentReturnsCorrectValue(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Autorescue',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' )
		);
		$this->assertTrue( $authorisation->isSuccessfulAutoRescue() );
	}

	public function testSuccessfulAutoRescueMessageTransferredToJobsAdyenQueue(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Autorescue',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$this->assertEquals( $msg['retryRescueReference'], $authorisation->retryRescueReference );
		$this->assertEquals( $msg['pspReference'], $authorisation->pspReference );
		$this->assertEquals( "SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob", $msg['php-message-class'] );
	}

	public function testSuccessfulAutoRescueAuthorisationMessageCapture(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$capture = JsonSerializableObject::fromJsonProxy( $msg['php-message-class'], json_encode( $msg ) );
		$approvePaymentResult = AdyenTestConfiguration::getSuccessfulApproveResult();
		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => 10,
				'currency' => 'USD',
				'gateway_txn_id' => $authorisation->pspReference
			] )
			->willReturn( $approvePaymentResult );

		$capture->execute();
		$this->assertEquals( $msg['merchantReference'], $authorisation->merchantReference );
		$this->assertEquals( $msg['shopperReference'], $authorisation->shopperReference );

		$recurringMsg = $this->recurringQueue->pop();
		$this->assertNotNull( $recurringMsg );
		$this->assertEquals( $recurringMsg['rescue_reference'], $authorisation->retryRescueReference );
		$this->assertEquals( $recurringMsg['gateway_txn_id'], $authorisation->pspReference );
		$this->assertEquals( $recurringMsg['txn_type'], 'subscr_payment' );
		$this->assertTrue( $recurringMsg['is_successful_autorescue'] );
	}

	public function testSuccessfulAutoRescueAuthorisationMessageCaptureJPY(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth_jpy.json' )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$capture = JsonSerializableObject::fromJsonProxy( $msg['php-message-class'], json_encode( $msg ) );
		$approvePaymentResult = AdyenTestConfiguration::getSuccessfulApproveResult();
		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => 335,
				'currency' => 'JPY',
				'gateway_txn_id' => $authorisation->pspReference
			] )
			->willReturn( $approvePaymentResult );

		$capture->execute();
		$this->assertEquals( $msg['merchantReference'], $authorisation->merchantReference );
		$this->assertEquals( $msg['shopperReference'], $authorisation->shopperReference );

		$recurringMsg = $this->recurringQueue->pop();
		$this->assertNotNull( $recurringMsg );
		$this->assertEquals( $recurringMsg['rescue_reference'], $authorisation->retryRescueReference );
		$this->assertEquals( $recurringMsg['gateway_txn_id'], $authorisation->pspReference );
		$this->assertEquals( $recurringMsg['txn_type'], 'subscr_payment' );
		$this->assertTrue( $recurringMsg['is_successful_autorescue'] );
	}

	public function testNoCaptureForAutoRescueMessage(): void {
		$authorisation = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Autorescue',
			file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$capture = JsonSerializableObject::fromJsonProxy( $msg['php-message-class'], json_encode( $msg ) );

		$capture->execute();

		$recurringMsg = $this->recurringQueue->pop();
		$this->assertNull( $recurringMsg );
	}

	public function testEndedAutoRescueAuth(): void {
		$authorisation = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/ended_auto_rescue_auth.json' ), true )
		);
		/** @var Authorisation $authorisation $action */
		$authorisation->runActionChain();

		$jobMsg = $this->jobsAdyenQueue->pop();
		$this->assertNull( $jobMsg );
		$recurMsg = QueueWrapper::getQueue( 'recurring' )->pop();
		$this->assertNotNull( $recurMsg );

		SourceFields::removeFromMessage( $recurMsg );
		$this->assertEquals( [
			'txn_type' => 'subscr_cancel',
			'rescue_reference' => $authorisation->retryRescueReference,
			'is_autorescue' => true,
			'cancel_reason' => 'Payment cannot be rescued: maximum failures reached'
		], $recurMsg );
	}
}
