<?php
namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\PayPal\Listener;
use SmashPig\Tests\BaseSmashPigUnitTestCase;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Http\Request;
use SmashPig\Core\DataStores\JsonSerializableObject;

/**
 * Test the IPN listener which receives messages, stores and processes them.
 * @group PayPal
 */
class CaptureIncomingMessageTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var ProviderConfiguration
	 */
	public $config;

	static $fail_verification = false;
	static $paypal_is_broken = false;

	// filename and the queue it should get dropped in
	static $message_data = array(
		'web_accept.json' => 'donations',
		'express_checkout.json' => 'donations',
		'recurring_payment_profile_created.json' => 'recurring',
		'subscr_signup.json' => 'recurring',
		'subscr_payment.json' => 'recurring',
		'recurring_payment.json' => 'recurring',
		'refund.json' => 'refund',
		'refund_ec.json' => 'refund',
		'refund_recurring_ec.json' => 'refund',
		'chargeback_settlement.json' => 'refund',
		// this should not actually get written to
		// TODO 'new_case.json' => 'no-op',
	);

	public function setUp() {
		parent::setUp();
		$this->config = Context::get()->getGlobalConfiguration();
		Context::get()->setProviderConfiguration(
			PayPalTestConfiguration::get( $this->config )
		);
	}

	public function tearDown() {
		self::$fail_verification = false;
		self::$paypal_is_broken = false;
		parent::tearDown();
	}

	public function messageProvider() {
		$messages = array();
		foreach ( self::$message_data as $file => $type ) {
			$payloadFile = __DIR__ . '/../Data/' . $file;
			$messageData = array(
				'type' => $type,
				'payload' => json_decode(
					file_get_contents( $payloadFile ),
					true
				)
			);
			$transformedFile = str_replace( '.json', '_transformed.json', $payloadFile );
			if ( file_exists( $transformedFile ) ) {
				$messageData['transformed'] = json_decode(
					file_get_contents( $transformedFile ),
					true
				);
			}
			$messages[] = array( $messageData );
		}
		return $messages;
	}

	private function capture( $msg ) {
		$request = new Request( $msg );
		$response = new Response;
		$listener = new Listener;
		return $listener->execute( $request, $response );
	}

	/**
	 * @dataProvider messageProvider
	 */
	public function testCapture( $msg ) {
		$this->capture( $msg['payload'] );

		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$this->assertEquals( $jobMessage['php-message-class'],
			'SmashPig\PaymentProviders\PayPal\Job' );

		$this->assertEquals( $jobMessage['payload'], $msg['payload'] );
	}

	public function testBlankMessage() {
		$this->capture( array() );
		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$this->assertNull( $jobQueue->pop() );
	}

	/**
	 * @dataProvider messageProvider
	 */
	public function testConsume( $msg ) {
		$this->capture( $msg['payload'] );

		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$job = JsonSerializableObject::fromJsonProxy(
			$jobMessage['php-message-class'],
			json_encode( $jobMessage )
		);

		$job->execute();

		$queue = $this->config->object( 'data-store/' . $msg['type'] );
		$message = $queue->pop();

		if ( $job->is_reject() ) {
			$this->assertEmpty( $message );
		} else {
			$this->assertNotEmpty( $message );
			if ( isset( $message['contribution_tracking_id'] ) ) {
				$this->assertEquals( $message['contribution_tracking_id'], $message['order_id'] );
			}

			if ( isset( $message['supplemental_address_1'] ) ) {
				$this->assertNotEquals(
					$message['supplemental_address_1'],
					"{$message['first_name']} {$message['last_name']}"
				);
			}
			if ( isset( $msg['transformed'] ) ) {
				SourceFields::removeFromMessage( $message );
				$this->assertEquals( $msg['transformed'], $message );
			}
		}
	}

	public function testFailedVerification() {
		self::$fail_verification = true;
		$jobMessage = array( 'txn_type' => 'fail' );
		$this->assertFalse( $this->capture( $jobMessage ) );
	}

	// FIXME: not really testing anything. Would like to verify that it tried
	// N times. Bubble that information up somehow.
	public function testPayPalIsBroken() {
		self::$paypal_is_broken = true;
		$jobMessage = array( 'txn_type' => 'fail' );
		$this->assertFalse( $this->capture( $jobMessage ) );
	}
}
