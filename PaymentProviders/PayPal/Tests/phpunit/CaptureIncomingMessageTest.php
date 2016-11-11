<?php
namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\PayPal\Listener;
use SmashPig\Tests\BaseSmashPigUnitTestCase;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Http\Request;
use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;

/**
 * Test the IPN listener which receives messages, stores and processes them.
 * @group PayPal
 */
class CaptureIncomingMessageTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Configuration
	 */
	public $config;

	static $fail_verification = false;

	// filename and the queue it should get dropped in
	static $message_data = array(
		'web_accept.json' => 'verified',
		'subscr_signup.json' => 'recurring',
		'subscr_payment.json' => 'recurring',
		'refund.json' => 'refund',
		'chargeback_settlement.json' => 'refund',
		// this should not actually get written to
		// TODO 'new_case.json' => 'no-op',
	);

	static $messages = array();

	public function setUp() {
		parent::setUp();
		$this->config = PayPalTestConfiguration::get();

		// php-queue\PDO complains about pop() from non-existent table
		$this->config->object( 'data-store/jobs-paypal' )
			->createTable( 'jobs-paypal' );

		Context::initWithLogger( $this->config );
		foreach ( self::$message_data as $file => $type ) {
			self::$messages[] = array(
				'type' => $type,
				'payload' => json_decode(
					file_get_contents( __DIR__ . '/../Data/' . $file ),
					true
				)
			);
		}
	}

	private function capture( $msg ) {
		$request = new Request( $msg );
		$response = new Response;
		$listener = new Listener;
		return $listener->execute( $request, $response );
	}

	public function testCapture() {
		foreach ( self::$messages as $msg ) {

			$this->capture( $msg['payload'] );

			$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
			$jobMessage = $jobQueue->pop();

			$this->assertEquals( $jobMessage['php-message-class'],
				'SmashPig\PaymentProviders\PayPal\Job' );

			$this->assertEquals( $jobMessage['payload'], $msg['payload'] );
		}
	}

	public function testBlankMessage() {
		$this->capture( array() );
		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$this->assertNull( $jobQueue->pop() );
	}

	public function testConsume() {
		foreach ( self::$messages as $msg ) {
			$this->capture( $msg['payload'] );

			$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
			$jobMessage = $jobQueue->pop();

			$job = KeyedOpaqueStorableObject::fromJsonProxy(
				$jobMessage['php-message-class'],
				json_encode( $jobMessage )
			);

			$job->execute();

			$queue = $this->config->object( 'data-store/' . $msg['type'] );
			$queue->createTable( $msg['type'] );
			$message = $queue->pop();

			if ( $job->is_reject() ) {
				$this->assertEmpty( $message );
			} else {
				$this->assertNotEmpty( $message );
				if ( isset( $message['contribution_tracking_id'] ) ) {
					$this->assertEquals( $message['contribution_tracking_id'], $message['order_id'] );
				}
			}

		}
	}

	public function testFailedVerification() {
		self::$fail_verification = true;
		$jobMessage = array( 'txn_type' => 'fail' );
		$this->assertFalse( $this->capture( $jobMessage ) );
	}

}
