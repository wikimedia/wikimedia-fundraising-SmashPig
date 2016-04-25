<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Adyen Capture job functions
 */
class CaptureJobTest extends BaseSmashPigUnitTestCase {

	public $config;

	public function setUp() {
		parent::setUp();

		$this->config = $this->setConfig( 'adyen', __DIR__ . '/../config_test_success.yaml' );
	}

	/**
	 * For a legit donation, ProcessCaptureJob should leave donor data
	 * on the pending queue, add an antifraud message, and return true.
	 */
	public function testSuccessfulCapture() {
		$antifraudQueue = $this->config->object( 'data-store/antifraud', true );
		$pendingQueue = $this->config->object( 'data-store/pending', true );
		$api = $this->config->object( 'payment-provider/adyen/api', true );

		$pendingQueue->addObject(
			KeyedOpaqueStorableObject::fromJsonProxy(
				'SmashPig\CrmLink\Messages\DonationInterfaceMessage',
				file_get_contents( __DIR__ . '/../Data/pending.json' )
			)
		);
		$auth = KeyedOpaqueStorableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$job = ProcessCaptureRequestJob::factory( $auth );
		$this->assertTrue( $job->execute() );

		$donorData = $pendingQueue->queueGetObject( null, $auth->correlationId );
		$this->assertNotNull(
			$donorData,
			'RequestCaptureJob did not leave donor data on pending queue'
		);
		$this->assertTrue(
			$donorData->captured,
			'RequestCaptureJob did not mark donor data as captured'
		);

		$this->assertEquals(
			array(
				'currency' => 'USD',
				'amount' => 10,
				'pspReference' => '762895314225',
			),
			$api->captured[0],
			'RequestCaptureJob did not make the right capture call'
		);

		// Blank correlation ID on antifraud messages
		$antifraudMessage = $antifraudQueue->queueGetObject( null, '' );
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'process',
			$antifraudMessage->validation_action,
			'Successful capture should get "process" validation action'
		);
	}

	/**
	 * When AVS and CVV scores push the donation over the review threshold,
	 * we should not capture the payment, but leave the donor details.
	 */
	public function testReviewThreshold() {
		$antifraudQueue = $this->config->object( 'data-store/antifraud', true );
		$pendingQueue = $this->config->object( 'data-store/pending', true );
		$api = $this->config->object( 'payment-provider/adyen/api', true );

		$pendingQueue->addObject(
			KeyedOpaqueStorableObject::fromJsonProxy(
				'SmashPig\CrmLink\Messages\DonationInterfaceMessage',
				file_get_contents( __DIR__ . '/../Data/pending.json' )
			)
		);
		$auth = KeyedOpaqueStorableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$auth->avsResult = '1'; // Bad zip code pushes us over review

		$job = ProcessCaptureRequestJob::factory( $auth );
		$this->assertTrue( $job->execute() );

		$donorData = $pendingQueue->queueGetObject( null, $auth->correlationId );
		$this->assertNotNull(
			$donorData,
			'RequestCaptureJob did not leave donor data for review'
		);
		$this->assertNotEquals(
			true,
			$donorData->captured,
			'RequestCaptureJob marked donor data above review threshold as captured'
		);

		$this->assertEmpty(
			$api->captured,
			'RequestCaptureJob tried to capture above review threshold'
		);

		$antifraudMessage = $antifraudQueue->queueGetObject( null, '' );
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'review',
			$antifraudMessage->validation_action,
			'Suspicious auth should get "review" validation action'
		);
	}

	/**
	 * When AVS and CVV scores push the donation over the reject threshold,
	 * we should cancel the authorization and delete the donor details.
	 */
	public function testRejectThreshold() {
		$antifraudQueue = $this->config->object( 'data-store/antifraud', true );
		$pendingQueue = $this->config->object( 'data-store/pending', true );
		$api = $this->config->object( 'payment-provider/adyen/api', true );

		$pendingQueue->addObject(
			KeyedOpaqueStorableObject::fromJsonProxy(
				'SmashPig\CrmLink\Messages\DonationInterfaceMessage',
				file_get_contents( __DIR__ . '/../Data/pending.json' )
			)
		);
		$auth = KeyedOpaqueStorableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$auth->avsResult = '2'; // No match at all
		$auth->cvvResult = '2'; // CVV is also wrong

		$job = ProcessCaptureRequestJob::factory( $auth );
		$this->assertTrue( $job->execute() );

		$donorData = $pendingQueue->queueGetObject( null, $auth->correlationId );
		$this->assertNull(
			$donorData,
			'RequestCaptureJob should delete fraudy donor data'
		);

		$this->assertEmpty(
			$api->captured,
			'RequestCaptureJob tried to capture above reject threshold'
		);
		$this->assertEquals(
			$auth->pspReference,
			$api->cancelled[0],
			'Did not cancel the fraudulent authorization'
		);

		$antifraudMessage = $antifraudQueue->queueGetObject( null, '' );
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'reject',
			$antifraudMessage->validation_action,
			'Obvious fraud should get "reject" validation action'
		);
	}

	/**
	 * When two authorizations come in with the same merchant reference, we
	 * should cancel the second one and leave the donor details in pending.
	 */
	public function testDuplicateAuthorisation() {
		$pendingQueue = $this->config->object( 'data-store/pending', true );
		$api = $this->config->object( 'payment-provider/adyen/api', true );

		$pendingQueue->addObject(
			KeyedOpaqueStorableObject::fromJsonProxy(
				'SmashPig\CrmLink\Messages\DonationInterfaceMessage',
				file_get_contents( __DIR__ . '/../Data/pending.json' )
			)
		);
		$auth1 = KeyedOpaqueStorableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);
		$job1 = ProcessCaptureRequestJob::factory( $auth1 );
		$job1->execute();

		$this->assertEquals( 1, count( $api->captured ), 'Set up failed' );

		$auth2 = KeyedOpaqueStorableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);
		$auth2->pspReference = mt_rand( 1000000000, 10000000000 );
		$job2 = ProcessCaptureRequestJob::factory( $auth2 );
		$this->assertTrue(
			$job2->execute(),
			'Duplicate auths should not clutter damage queue'
		);

		$this->assertEquals( 1, count( $api->captured ), 'Captured a duplicate!' );
		$this->assertEquals(
			$auth2->pspReference,
			$api->cancelled[0],
			'Did not cancel the right authorization'
		);

		$this->assertNotNull(
			$pendingQueue->queueGetObject( null, $auth1->correlationId ),
			'Capture job should leave donor details on queue'
		);
	}

}
