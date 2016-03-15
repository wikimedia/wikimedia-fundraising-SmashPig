<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;

/**
 * Verify Adyen Capture job functions
 */
class CaptureJobTest extends \BaseSmashPigUnitTestCase {

	/**
	 * For a legit donation, ProcessCaptureJob should leave donor data
	 * on the pending queue, add an antifraud message, and return true.
	 */
	public function testSuccessfulCapture() {
		$this->setConfig( __DIR__ . '/../config_test_success.php', 'adyen' );
		$antifraudQueue = Configuration::getDefaultConfig()->object( 'data-store/antifraud', true );
		$pendingQueue = Configuration::getDefaultConfig()->object( 'data-store/pending', true );
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
		$this->assertNotNull(
			$pendingQueue->queueGetObject( null, $auth->correlationId ),
			'RequestCaptureJob did not leave donor data on pending queue'
		);
		$this->assertNotNull(
			// Blank correlation ID on antifraud messages
			$antifraudQueue->queueGetObject( null, "" ),
			'RequestCaptureJob did not send antifraud message'
		);
	}

}
