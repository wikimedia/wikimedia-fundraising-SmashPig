<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Make sure a message that arrives before the pending databases entry gets
 * written is properly requeued for retry.
 */
class RequeueMessageTest extends BaseSmashPigUnitTestCase {

	/**
	 * @expectedException \SmashPig\Core\RetryableException
	 */
	public function testRequeueMessage() {

		$auth = KeyedOpaqueStorableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$job = ProcessCaptureRequestJob::factory( $auth );
		$job->execute();

	}

}
