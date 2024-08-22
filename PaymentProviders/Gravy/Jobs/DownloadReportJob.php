<?php namespace SmashPig\PaymentProviders\Gravy\Jobs;

use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Runnable;

/**
 * Download Gravy settlement detail reports. These reports are named
 * gravy_settlement_report_Y_m_d.csv
 *
 * @package SmashPig\PaymentProviders\Gravy\Jobs
 */
class DownloadReportJob implements Runnable {
	public array $payload;

	protected TaggedLogger $logger;

	protected string $downloadLoc;

	public static function factory( $message ): array {
		return [
			'class' => 'SmashPig\PaymentProviders\Gravy\Jobs\DownloadReportJob',
			'payload' => $message
		];
	}

	public function execute() {
		// TODO: Do download process
	}
}
