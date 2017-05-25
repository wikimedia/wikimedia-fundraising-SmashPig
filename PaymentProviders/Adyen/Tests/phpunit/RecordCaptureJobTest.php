<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\JsonSerializableObject;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Adyen\Jobs\RecordCaptureJob;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Adyen RecordCapture job functions
 *
 * @group Adyen
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

	public function setUp() {
		parent::setUp();
		$this->config = AdyenTestConfiguration::createWithSuccessfulApi();
		Context::initWithLogger( $this->config );
		$this->pendingDatabase = PendingDatabase::get();
		$this->pendingDatabase->createTable();
		$this->pendingMessage = json_decode(
			file_get_contents( __DIR__ . '/../Data/pending.json' ) , true
		);
		$this->pendingMessage['captured'] = true;
		$this->pendingDatabase->storeMessage( $this->pendingMessage );
	}

	public function tearDown() {
		$this->pendingDatabase->deleteMessage( $this->pendingMessage );
		parent::tearDown();
	}

	public function testRecordCapture() {
		$donationsQueue = QueueWrapper::getQueue( 'donations' );
		$donationsQueue->createTable( 'donations' );

		$capture = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture',
			file_get_contents( __DIR__ . '/../Data/capture.json' )
		);

		$job = RecordCaptureJob::factory( $capture );
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'adyen', $capture->merchantReference
		);

		$this->assertNull(
			$donorData,
			'RecordCaptureJob left donor data on pending queue'
		);

		$donationMessage = $donationsQueue->pop();
		$this->assertNotNull(
			$donationMessage,
			'RecordCaptureJob did not send donation message'
		);
		// can we use arraySubset yet?
		$sameKeys = array_intersect(
			array_keys( $donationMessage ),
			array_keys( $this->pendingMessage )
		);
		foreach ( $sameKeys as $key ) {
			if ( $key === 'gateway_txn_id' ) {
				$this->assertEquals(
					$capture->originalReference, $donationMessage[$key],
					'RecordCaptureJob should have set gateway_txn_id'
				);
			} else {
				$this->assertEquals(
					$this->pendingMessage[$key],
					$donationMessage[$key],
					"Value of key $key mutated"
				);
			}
		}
	}
}
