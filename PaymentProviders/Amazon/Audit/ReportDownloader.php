<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

use SmashPig\Core\Logging\Logger;
use PayWithAmazon\ReportsClient;

/**
 * Downloads transaction reports via MWS
 */
class ReportDownloader {

	protected $archivePath;
	protected $downloadPath;
	protected $days;
	protected $downloadedIds = array();
	protected $clientConfig;
	protected $reportsClient;

	const FILE_REGEX = '/\d{4}-\d{2}-\d{2}-[_A-Z0-9]+_(?P<id>\d+).csv/';

	public function __construct( $config ) {
		$this->archivePath = $config['ArchivePath'];
		$this->downloadPath = $config['DownloadPath'];
		$this->days = $config['Days'];
		$this->clientConfig = array(
			'merchant_id' => $config['SellerID'],
			'access_key' => $config['MWSAccessKey'],
			'secret_key' => $config['MWSSecretKey'],
			'client_id' => $config['ClientID'],
			'region' => $config['Region'],
		);
	}

	protected function getReportsClient( $config ) {
		return new ReportsClient( $config );
	}

	protected function ensureAndScanFolder( $path ) {
		if ( !is_dir( $path ) ) {
			if ( file_exists( $path ) ) {
				throw new \RuntimeException( "$path exists and is not a directory!" );
			}
			Logger::info( "Creating missing directory $path" );
			if ( !mkdir( $path ) ) {
				throw new \RuntimeException( "Unable to create directory $path!" );
			}
		}
		foreach ( scandir( $path ) as $file ) {
			if ( preg_match( self::FILE_REGEX, $file, $matches ) ) {
				$this->downloadedIds[] = $matches['id'];
			}
		}
	}

	public function download() {
		$this->ensureAndScanFolder( $this->archivePath );
		$this->ensureAndScanFolder( $this->downloadPath );

		$this->reportsClient = $this->getReportsClient( $this->clientConfig );
		// TODO: use AvailableFromDate and ReportTypeList parameters
		Logger::info( 'Getting report list' );
		$list = $this->reportsClient->getReportList()->toArray();
		foreach ( $list['GetReportListResult']['ReportInfo'] as $reportInfo ) {
			// If you're planning to download more than 15 reports at a time, be
			// aware that the client will handle throttling by default, retrying
			// up to four times with successively longer wait times.
			$this->downloadReport( $reportInfo );
		}
	}

	protected function downloadReport( $reportInfo ) {
		$id = $reportInfo['ReportId'];
		// Remove common prefix from report type
		$type = str_replace(
			'_GET_FLAT_FILE_OFFAMAZONPAYMENTS_',
			'',
			$reportInfo['ReportType']
		);
		if ( array_search( $id, $this->downloadedIds ) === false ) {
			Logger::debug( "Downloading report with id: $id" );
			$report = $this->reportsClient->getReport( array(
				'report_id' => $id,
			) );
			$date = substr( $reportInfo['AvailableDate'], 0, 10 );
			$path = "{$this->downloadPath}/{$date}-{$type}{$id}.csv";
			Logger::info( "Saving report to $path" );
			file_put_contents( $path, $report['ResponseBody'] );
		} else {
			Logger::debug( "Skipping downloaded report with id: $id" );
		}
	}
}
