<?php
namespace SmashPig\PaymentProviders\Adyen\Tests;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Adyen\Actions\CaptureResponseAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Adyen
 */
class CaptureResponseActionTest extends BaseAdyenTestCase {

	/**
	 * @var FifoQueueStore
	 */
	protected $jobQueue;

	public function setUp() {
		parent::setUp();
		$this->jobQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-adyen' );
	}

	public function testSuccessfulCapture() {
		$capture = new Capture();
		$capture->success = true;

		$capture->merchantAccountCode = 'WikimediaTest';
		$capture->currency = 'USD';
		$capture->amount = 10.00;
		$capture->originalReference = mt_rand();
		$capture->merchantReference = mt_rand();

		$action = new CaptureResponseAction();
		$action->execute( $capture );

		$job = $this->jobQueue->pop();

		$this->assertEquals(
			'SmashPig\PaymentProviders\Adyen\Jobs\RecordCaptureJob',
			$job['php-message-class']
		);
		$sameProps = [
			'currency', 'amount', 'originalReference', 'merchantReference'
		];
		foreach ( $sameProps as $prop ) {
			$this->assertEquals(
				$capture->$prop,
				$job[$prop],
				"Job property $prop does not match capture"
			);
		}
	}

	public function testFailedCapture() {
		$capture = new Capture();
		$capture->success = false;

		$action = new CaptureResponseAction();
		$action->execute( $capture );

		$job = $this->jobQueue->pop();

		$this->assertNull( $job, 'Should not have queued a job' );
	}

}
