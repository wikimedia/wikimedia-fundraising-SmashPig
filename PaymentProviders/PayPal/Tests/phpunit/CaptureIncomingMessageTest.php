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
	static $verified_msg;

	public function setUp() {
		parent::setUp();
		$this->config = PayPalTestConfiguration::get();
		Context::initWithLogger( $this->config );
		self::$verified_msg = json_decode(
			file_get_contents( __DIR__ . '/../Data/web_accept.json' ),
			true
		);
	}

	private function capture ( $msg ) {
		$request = new Request( $msg );
		$response = new Response;
		$listener = new Listener;
		$listener->execute( $request, $response );
	}

	public function testCapture() {

		$this->capture( self::$verified_msg );

		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$this->assertEquals( $jobMessage['php-message-class'],
			'SmashPig\PaymentProviders\PayPal\Job' );

		$this->assertEquals( $jobMessage['payload'], self::$verified_msg );

	}

	public function testConsume () {

		$this->capture( self::$verified_msg );

		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$job = KeyedOpaqueStorableObject::fromJsonProxy(
			$jobMessage['php-message-class'],
			json_encode( $jobMessage )
		);

		$job->execute();

		$verifiedQueue = $this->config->object( 'data-store/verified' );
		$verifiedMessage = $verifiedQueue->pop();

		$this->assertNotEmpty( $verifiedMessage );

	}

	public function testFailedConsume () {
		$jobMessage = array('just' => 'some', 'old' => 'message' );
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
