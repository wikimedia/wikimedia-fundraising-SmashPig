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
	private $verified_msg;

	public function setUp() {
		parent::setUp();
		$this->config = PayPalTestConfiguration::get();
		Context::initWithLogger( $this->config );
		$this->verified_msg = json_decode(
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

		$this->capture( $this->verified_msg );

		// TODO why get it from BaseQueueConsumer instead of config?
		$jobQueue = BaseQueueConsumer::getQueue( 'jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$this->assertEquals( $jobMessage['php-message-class'],
			'SmashPig\PaymentProviders\PayPal\Job' );

		$this->assertEquals( $jobMessage['payload'], $this->verified_msg );

	}

	public function testConsume () {

		$this->capture( $this->verified_msg );

		// TODO DRY?
		$jobQueue = BaseQueueConsumer::getQueue( 'jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$job = KeyedOpaqueStorableObject::fromJsonProxy(
			$jobMessage['php-message-class'],
			json_encode( $jobMessage )
		);

		$job->execute();

		$verifiedQueue = $this->config->object( 'data-store/verified' );
		$verifiedMessage = $verifiedQueue->pop();

		// TODO can we verify that it looks right after transmogrification? might be
		// a job for the crm consumer
		$this->assertTrue( ! empty( $verifiedMessage ) );

	}
}
