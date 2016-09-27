<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\QueueConsumers\BaseQueueConsumer;
use SmashPig\PaymentProviders\Adyen\Jobs\RecordCaptureJob;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\Tests\BaseSmashPigUnitTestCase;
use SmashPig\Tests\PaymentsInitialDatabaseTest;
use SmashPig\Tests\TestingDatabase;

/**
 * Verify Adyen RecordCapture job functions
 */
class RecordCaptureJobTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Configuration
	 */
	public $config;
	/**
	 * @var PendingDatabase
	 */
	protected $pendingDatabase;
	protected $pendingMessage;
	/**
	 * @var PaymentsInitialDatabase
	 */
	protected $paymentsInitDatabase;
	protected $paymentsInitMessage;

	public function setUp() {
		parent::setUp();
		$this->config = AdyenTestConfiguration::createWithSuccessfulApi();
		Context::initWithLogger( $this->config );

		$this->paymentsInitDatabase = PaymentsInitialDatabase::get();
		$this->paymentsInitDatabase->createTable();
		$this->paymentsInitMessage = PaymentsInitialDatabaseTest::generateTestMessage();
		$this->paymentsInitMessage['gateway'] = 'adyen';
		$this->paymentsInitMessage['payments_final_status'] = 'pending';
		$this->paymentsInitDatabase->storeMessage(
			$this->paymentsInitMessage
		);

		$this->pendingDatabase = PendingDatabase::get();
		$this->pendingDatabase->createTable();
		$this->pendingMessage = json_decode(
			file_get_contents( __DIR__ . '/../Data/pending.json' ) , true
		);
		foreach( array( 'order_id', 'contribution_tracking_id' ) as $key ) {
			$this->pendingMessage[$key] = $this->paymentsInitMessage[$key];
		}
		$this->pendingMessage['correlationId'] = 'adyen-' .
			$this->pendingMessage['order_id'];
		$this->pendingMessage['captured'] = true;
		$this->pendingDatabase->storeMessage( $this->pendingMessage );
	}

	public function tearDown() {
		TestingDatabase::clearStatics( $this->paymentsInitDatabase );
		TestingDatabase::clearStatics( $this->pendingDatabase );
		parent::tearDown();
	}

	public function testRecordCapture() {
		$verifiedQueue = BaseQueueConsumer::getQueue( 'verified' );
		$verifiedQueue->createTable( 'verified' );

		$capture = KeyedOpaqueStorableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture',
			file_get_contents( __DIR__ . '/../Data/capture.json' )
		);
		$capture->merchantReference = $this->pendingMessage['order_id'];

		$job = RecordCaptureJob::factory( $capture );
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'adyen', $capture->merchantReference
		);

		$this->assertNull(
			$donorData,
			'RecordCaptureJob left donor data on pending queue'
		);

		$verifiedMessage = $verifiedQueue->pop();
		$this->assertNotNull(
			$verifiedMessage,
			'RecordCaptureJob did not send verified message'
		);
		// can we use arraySubset yet?
		$sameKeys = array_intersect(
			array_keys( $verifiedMessage ),
			array_keys( $this->pendingMessage )
		);
		foreach ( $sameKeys as $key ) {
			if ( $key === 'gateway_txn_id' ) {
				$this->assertEquals(
					$capture->originalReference, $verifiedMessage[$key],
					'RecordCaptureJob should have set gateway_txn_id'
				);
			} else {
				$this->assertEquals(
					$this->pendingMessage[$key],
					$verifiedMessage[$key],
					"Value of key $key mutated"
				);
			}
		}
		$initMessage = $this->paymentsInitDatabase
			->fetchMessageByGatewayOrderId(
				'adyen', $this->pendingMessage['order_id']
			);

		$this->assertEquals(
			'complete',
			$initMessage['payments_final_status'],
			'Did not mark payments_initial row as complete'
		);
	}
}
