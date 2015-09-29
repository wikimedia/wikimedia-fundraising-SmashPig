<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

require( __DIR__ . '/../../../Maintenance/MaintenanceBase.php' );

use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\Core\Context;

$maintClass = '\SmashPig\PaymentProviders\Amazon\Audit\DownloadReports';

/**
 * Command-line script to download new audit reports via MWS
 */
class DownloadReports extends MaintenanceBase {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'days', 'Number of days of reports to download', 7, 'd' );
		// Override the default config node
		$this->desiredOptions['config-node']['default'] = 'amazon';
	}

	public function execute() {
		$config = Context::get()->getConfiguration();
		$downloaderConfig = $config->val( 'credentials' );
		$downloaderConfig['ArchivePath'] = $config->val( 'audit/archive-path' );
		$downloaderConfig['DownloadPath'] = $config->val( 'audit/download-path' );
		$downloaderConfig['Days'] = $this->getOption( 'days' );
		$downloader = new ReportDownloader( $downloaderConfig );
		$downloader->download();
	}
}

require( RUN_MAINTENANCE_IF_MAIN );