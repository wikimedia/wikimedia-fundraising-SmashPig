<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\QueueConsumers\JobQueueConsumer;
use SmashPig\PaymentProviders\dlocal\ExpatriatedMessages\RejectedMessage;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class RejectedMessageJobTest extends BaseSmashPigUnitTestCase {

	public function testRejectionMessagePushedToQueueIfTypeIsWalletDisabled() : void {
		// set up the test RejectedMessage object
		$rejectedIpnFixture = file_get_contents( __DIR__ . '/../Data/rejected-322-ipn.json' );
		$rejectedMessage = RejectedMessage::fromJson(
			RejectedMessage::class,
			$rejectedIpnFixture
		);

		// set up the RejectedMessageJob using the RejectedMessage test
		$rejectedMessageJob = JobQueueConsumer::createJobObject(
			$rejectedMessage->normalizeForQueue()
		);

		// check that the job completes successfully
		$this->assertTrue( $rejectedMessageJob->execute() );

		// check that a rejection message is pushed to upi-donations queue to be processed
		$upiDonationsMessage = QueueWrapper::getQueue( 'upi-donations' )->pop();
		$this->assertNotNull( $upiDonationsMessage, 'upi-donations queue should not be empty!' );
	}

	public function testRejectionMessageNotPushedToQueueIfTypeIsGeneral() : void {
		// set up the test RejectedMessage object
		$rejectedIpnFixture = file_get_contents( __DIR__ . '/../Data/rejected-ipn.json' );
		$rejectedMessage = RejectedMessage::fromJson(
			RejectedMessage::class,
			$rejectedIpnFixture
		);

		// set up the RejectedMessageJob using the RejectedMessage test
		$rejectedMessageJob = JobQueueConsumer::createJobObject(
			$rejectedMessage->normalizeForQueue()
		);

		// check that job completes successfully
		$this->assertTrue( $rejectedMessageJob->execute() );

		// check that a message is not pushed to upi-donations queue. We only push 'Wallet disabled' rejections
		$upiDonationsMessage = QueueWrapper::getQueue( 'upi-donations' )->pop();
		$this->assertNull( $upiDonationsMessage, 'upi-donations queue should be empty!' );
	}

}
