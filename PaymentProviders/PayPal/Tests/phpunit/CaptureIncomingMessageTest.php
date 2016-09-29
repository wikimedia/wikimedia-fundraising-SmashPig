<?php
namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\QueueConsumers\BaseQueueConsumer;
use SmashPig\PaymentProviders\PayPal\Listener;
use SmashPig\PaymentProviders\PayPal\Job;
use SmashPig\PaymentProviders\PayPal\Tests\PayPalTestConfiguration;
use SmashPig\Tests\BaseSmashPigUnitTestCase;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Http\Request;
use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;

/**
 * Test the IPN listener which receives messages, stores and processes them.
 */
class CaptureIncomingMessageTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Configuration
	 */
	public $config;

	static $fail_verification = false;

	static $message_locations = array(
		'verified' => 'web_accept.json',
		'recurring' => 'subscr_signup.json',
		'recurring' => 'subscr_payment.json',
		'refund' => 'refund.json',
	);

	static $messages = array();

	public function setUp() {
		parent::setUp();
		$this->config = PayPalTestConfiguration::get();

		// php-queue\PDO complains about pop() from non-existent table
		$this->config->object( 'data-store/jobs-paypal' )
			->createTable( 'jobs-paypal' );

		Context::initWithLogger( $this->config );
		foreach ( self::$message_locations as $type => $file ) {
			self::$messages[$type] = json_decode(
				file_get_contents( __DIR__ . '/../Data/' . $file ),
				true
			);
		}
	}

	private function capture( $msg ) {
		$request = new Request( $msg );
		$response = new Response;
		$listener = new Listener;
		$listener->execute( $request, $response );
	}

	public function testCapture() {
		foreach ( self::$messages as $type => $msg ) {

			$this->capture( $msg );

			$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
			$jobMessage = $jobQueue->pop();

			$this->assertEquals( $jobMessage['php-message-class'],
				'SmashPig\PaymentProviders\PayPal\Job' );

			$this->assertEquals( $jobMessage['payload'], $msg );

		}
	}

	public function testBlankMessage() {
		$this->capture( array() );
		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$this->assertNull( $jobQueue->pop() );
	}

	public function testConsume() {
		foreach ( self::$messages as $type => $msg ) {
			$this->capture( $msg );

			$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
			$jobMessage = $jobQueue->pop();

			$job = KeyedOpaqueStorableObject::fromJsonProxy(
				$jobMessage['php-message-class'],
				json_encode( $jobMessage )
			);

			$job->execute();

			$queue = $this->config->object( 'data-store/' . $type );
			$queue->createTable( $type );
			$message = $queue->pop();

			$this->assertNotEmpty( $message );

		}
	}

	public function testFailedConsume() {
		self::$fail_verification = true;
		$jobMessage = array( 'txn_type' => 'fail' );
		$jobClass = 'SmashPig\PaymentProviders\PayPal\Job';
		$job = KeyedOpaqueStorableObject::fromJsonProxy(
			$jobClass,
			json_encode( $jobMessage )
		);

		try {
			$job->execute();
		} catch ( \Exception $e ) {
			// TODO I think this can throw a special exception to move to
			// damaged queue or some other stuff
			$this->assertEquals(
				\SmashPig\PaymentProviders\PayPal\Job::$verifyFailedMsg,
				$e->getMessage()
			);
		}

	}
}
