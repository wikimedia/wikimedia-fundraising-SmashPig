<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Context;
use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\SmashPigException;
use SmashPig\Core\Jobs\RunnableJob;

/**
 * Process Adyen end of day payment reports. These reports are named
 * payments_accounting_report_[yyyy]_[mm]_[dd].csv
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class ProcessAccountingReportJob extends RunnableJob {

	/** @var TaggedLogger */
	protected $logger;

	protected $account;
	protected $reportUrl;

	protected $downloadLoc;

	public static function factory( $account, $url ) {
		$obj = new ProcessAccountingReportJob();

		$obj->account = $account;
		$obj->reportUrl = $url;

		return $obj;
	}

	public function execute() {
		$this->logger = new TaggedLogger( __CLASS__ );
		$c = Context::get()->getConfiguration();

		// Construct the temporary file path
		$fileName = basename( $this->reportUrl );
		$this->downloadLoc =
			$c->val( "payment-provider/adyen/accounts/{$this->account}/report-location" ) . '/' .
			$fileName;

		// Actually get the file
		$this->downloadLog();

		// Now iterate through; finding all the ones marked Authorised and SentForSettle
		// The Authorised entries at the end that do not have a SentForSettle need to be
		// sent out again.
		$this->logger->debug( "Now starting Authorized -> SentForSettle search loop" );
		$f = new HeadedCsvReader( $this->downloadLoc );
		$tempQueue = array();

		foreach ( $f as $row ) {
			switch ( $f->extractCol( 'Record Type', $row ) ) {
				case 'Authorised':
					$tempQueue[$f->extractCol( 'Psp Reference', $row )] = $row;
					break;

				case 'SentForSettle':
					unset( $tempQueue[$f->extractCol( 'Psp Reference', $row )] );
					// TODO: Audit this row; probably send it to an audit queue
					break;

				default:
					// Don't care :)
					break;
			}
		}

		$tqc = count( $tempQueue );
		$this->logger->debug(
			"Of {$f->key()} rows, {$tqc} need to be reprocessed.",
			array( 'headers' => $f->headers(), 'values' => $tempQueue )
		);

		foreach ( $tempQueue as $pspRef => $row ) {
			$correlationId = 'adyen-' . $f->extractCol( 'Merchant Reference', $row );
			$this->logger->enterContext( "$correlationId" );

			$currency = $f->extractCol( 'Payment Currency', $row );
			$amount = floatval( $f->extractCol( 'Authorised (PC)', $row ) );
			$account = $f->extractCol( 'Merchant Account', $row );

			$this->logger->info(
				"Recreating Adyen capture job for {$currency} {$amount} with id {$correlationId} and " .
				"psp reference {$pspRef}."
			);
			$jobQueueObj = $c->obj( 'data-store/jobs' );
			$jobQueueObj->addObject(
				ProcessCaptureRequestJob::factory(
					$correlationId,
					$account,
					$currency,
					$amount,
					$pspRef
				)
			);

			$this->logger->leaveContext(); // Must be the last line in this loop
		}

		// OK; we're done here, delete the file
		unlink( $this->downloadLoc );

		return true;
	}

	protected function downloadLog() {
		$c = Context::get()->getConfiguration();

		$user = $c->val( "payment-provider/adyen/accounts/{$this->account}/report-username" );
		$pass = $c->val( "payment-provider/adyen/accounts/{$this->account}/report-password" );

		$this->logger->info(
			"Beginning log download from {$this->reportUrl} using username {$user} into {$this->downloadLoc}"
		);

		$fp = fopen( $this->downloadLoc, 'w' );
		if ( !$fp ) {
			$str = "Could not open {$this->downloadLoc} for writing! Will not download/process report.";
			$this->logger->error( $str );
			throw new SmashPigException( $str );
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->reportUrl);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_FILE, $fp );

		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
		curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}" );

		$result = curl_exec($ch);
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error = curl_error( $ch );
		curl_close( $ch );

		if ( $result === false ) {
			$this->logger->error( "Could not download report due to cURL error {$error}" );
			throw new SmashPigException( "Could not download report." );
		} elseif ( $httpCode !== 200 ) {
			$this->logger->error( "Report downloaded(?), but with incorrect HTTP code: {$httpCode}" );
			throw new SmashPigException( "Could not download report." );
		}
	}
}
