<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use PHPQueue\Backend\PDO;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\JsonSerializableObject;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Adyen Capture job functions
 *
 * @group Adyen
 */
class CaptureJobTest extends BaseAdyenTestCase {
	/**
	 * @var PendingDatabase
	 */
	protected $pendingDatabase;
	protected $pendingMessage;
	/**
	 * @var PDO
	 */
	protected $antifraudQueue;

	public function setUp() {
		parent::setUp();

		$this->pendingDatabase = PendingDatabase::get();
		$this->pendingMessage = json_decode(
			file_get_contents( __DIR__ . '/../Data/pending.json' ), true
		);
		$this->pendingDatabase->storeMessage( $this->pendingMessage );
		$this->antifraudQueue = QueueWrapper::getQueue( 'payments-antifraud' );
	}

	public function tearDown() {
		$this->pendingDatabase->deleteMessage( $this->pendingMessage );
		parent::tearDown();
	}

	/**
	 * For a legit donation, ProcessCaptureJob should leave donor data
	 * in the pending database, add an antifraud message, and return true.
	 */
	public function testSuccessfulCapture() {
		$api = $this->config->object( 'api', true );

		$auth = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$job = ProcessCaptureRequestJob::factory( $auth );
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'adyen', $auth->merchantReference
		);

		$this->assertNotNull(
			$donorData,
			'RequestCaptureJob did not leave donor data on pending queue'
		);
		$this->assertTrue(
			$donorData['captured'],
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

		$antifraudMessage = $this->antifraudQueue->pop();
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'process',
			$antifraudMessage['validation_action'],
			'Successful capture should get "process" validation action'
		);
	}

	/**
	 * When AVS and CVV scores push the donation over the review threshold,
	 * we should not capture the payment, but leave the donor details.
	 */
	public function testReviewThreshold() {
		$api = $this->config->object( 'api', true );

		$auth = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$auth->avsResult = '1'; // Bad zip code pushes us over review

		$job = ProcessCaptureRequestJob::factory( $auth );
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'adyen', $auth->merchantReference
		);
		$this->assertNotNull(
			$donorData,
			'RequestCaptureJob did not leave donor data for review'
		);
		$this->assertTrue(
			empty( $donorData['captured'] ),
			'RequestCaptureJob marked donor data above review threshold as captured'
		);

		$this->assertEmpty(
			$api->captured,
			'RequestCaptureJob tried to capture above review threshold'
		);

		$antifraudMessage = $this->antifraudQueue->pop();
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'review',
			$antifraudMessage['validation_action'],
			'Suspicious auth should get "review" validation action'
		);
	}

	/**
	 * When AVS and CVV scores push the donation over the reject threshold,
	 * we should cancel the authorization and delete the donor details.
	 */
	public function testRejectThreshold() {
		$api = $this->config->object( 'api', true );

		$auth = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$auth->avsResult = '2'; // No match at all
		$auth->cvvResult = '2'; // CVV is also wrong

		$job = ProcessCaptureRequestJob::factory( $auth );
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'adyen', $auth->merchantReference
		);
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

		$antifraudMessage = $this->antifraudQueue->pop();
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'reject',
			$antifraudMessage['validation_action'],
			'Obvious fraud should get "reject" validation action'
		);
	}

	/**
	 * When two authorizations come in with the same merchant reference, we
	 * should cancel the second one and leave the donor details in pending.
	 */
	public function testDuplicateAuthorisation() {
		$api = $this->config->object( 'api', true );

		$auth1 = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);
		$job1 = ProcessCaptureRequestJob::factory( $auth1 );
		$job1->execute();

		$this->assertEquals( 1, count( $api->captured ), 'Set up failed' );

		$auth2 = JsonSerializableObject::fromJsonProxy(
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
			$this->pendingDatabase->fetchMessageByGatewayOrderId(
				'adyen', $auth1->merchantReference
			),
			'Capture job should leave donor details in database'
		);
	}

}
