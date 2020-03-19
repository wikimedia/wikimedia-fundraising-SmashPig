<?php
namespace SmashPig\PaymentProviders\Adyen\Tests;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Adyen\Actions\PaymentCaptureAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;

/**
 * @group Adyen
 */
class PaymentCaptureActionTest extends BaseAdyenTestCase {

	/**
	 * @var FifoQueueStore
	 */
	protected $jobQueue;

	public function setUp() {
		parent::setUp();
		$globalConfig = Context::get()->getGlobalConfiguration();
		$this->jobQueue = $globalConfig->object( 'data-store/jobs-adyen' );
	}

	public function testSuccessfulAuth() {
		$auth = new Authorisation();
		$auth->success = true;
		$auth->merchantAccountCode = 'WikimediaTest';
		$auth->currency = 'USD';
		$auth->amount = '10';
		$auth->merchantReference = mt_rand();
		$auth->pspReference = mt_rand();
		$auth->cvvResult = 1;
		$auth->avsResult = 19;

		$action = new PaymentCaptureAction();
		$action->execute( $auth );

		$job = $this->jobQueue->pop();

		$this->assertEquals(
			'SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob',
			$job['php-message-class']
		);
		$sameProps = [
			'currency', 'amount', 'pspReference', 'merchantReference',
			'avsResult', 'cvvResult',

		];
		foreach ( $sameProps as $prop ) {
			$this->assertEquals(
				$auth->$prop,
				$job[$prop],
				"Job property $prop does not match capture"
			);
		}
	}

	public function testFailedAuth() {
		$auth = new Authorisation();
		$auth->success = false;
		$auth->merchantAccountCode = 'WikimediaTest';
		$auth->merchantReference = mt_rand();

		$action = new PaymentCaptureAction();
		$action->execute( $auth );

		$job = $this->jobQueue->pop();

		$this->assertEquals(
			'SmashPig\Core\Jobs\DeletePendingJob',
			$job['class']
		);
		$this->assertEquals(
			$auth->merchantReference,
			$job['payload']['order_id']
		);
		$this->assertEquals( 'adyen', $job['payload']['gateway'] );
	}
}
