<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\JsonSerializableObject;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Make sure a message that arrives before the pending databases entry gets
 * written is properly requeued for retry.
 *
 * @group Adyen
 */
class RequeueMessageTest extends BaseSmashPigUnitTestCase {

	public function setUp() {
		parent::setUp();
		$config = AdyenTestConfiguration::createWithSuccessfulApi();
		Context::get()->setProviderConfiguration( $config );
		PendingDatabase::get()->createTable();
	}

	/**
	 * @expectedException \SmashPig\Core\RetryableException
	 */
	public function testRequeueMessage() {

		$auth = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$job = ProcessCaptureRequestJob::factory( $auth );
		$job->execute();

	}

}
