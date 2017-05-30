<?php
namespace SmashPig\PaymentProviders\Adyen\Tests;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\UtcDate;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\ReportAvailable;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Adyen
 */
class ReportAvailableTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var AdyenTestConfiguration
	 */
	protected $config;

	/**
	 * @var FifoQueueStore
	 */
	protected $jobQueue;

	public function setUp() {
		parent::setUp();
		$this->config = AdyenTestConfiguration::createWithSuccessfulApi();
		Context::get()->setProviderConfiguration( $this->config );
		$this->jobQueue = QueueWrapper::getQueue( 'jobs-adyen' );
	}

	public function testReportAvailable() {
		$filename = 'settlement_detail_report_2016_10_13.csv';
		$account = 'WikimediaTest';
		$url = "https://example.com/reports/download/MerchantAccount/$account/$filename";
		$reportAvailable = new ReportAvailable();

		$reportAvailable->merchantAccountCode = $account;
		$reportAvailable->merchantReference = mt_rand();
		$reportAvailable->pspReference = $filename;
		$reportAvailable->reason = $url;
		$reportAvailable->eventDate = '2016-10-14T12:06:20.496+02:00';

		$reportAvailable->runActionChain();

		$job = $this->jobQueue->pop();

		$now = UtcDate::getUtcTimestamp();
		$diff = abs( $job['source_enqueued_time'] ) - $now;
		$this->assertTrue( $diff < 60, 'Odd enqueued time' );
		SourceFields::removeFromMessage( $job );
		unset( $job['propertiesExcludedFromExport'] );
		$expected = array(
			'php-message-class' => 'SmashPig\PaymentProviders\Adyen\Jobs\DownloadReportJob',
			'reportUrl' => $url,
			'account' => $account,
			'gateway' => 'adyen',
		);
		$this->assertEquals( $expected, $job );
	}

}
