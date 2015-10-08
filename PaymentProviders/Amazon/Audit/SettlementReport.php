<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\DataFiles\DataFileException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\UtcDate;

/**
 * Parses off-Amazon payments settlement reports retrieved from MWS
 * http://amazonpayments.s3.amazonaws.com/documents/Sample%20Settlement%20Report.pdf#page=15
 */
class SettlementReport {

	protected $fileData = array();

	public static function isMine( $filename ) {
		return preg_match( '/.*SETTLEMENT_DATA.*csv/', $filename );
	}

	public function parse( $path ) {
		// Skip 5 lines at start of file;
		$csv = new HeadedCsvReader( $path, ',', 4096, 5 );

		while ( $csv->valid() ) {
			try {
				$this->parseLine( $csv );
				$csv->next();
			} catch ( DataFileException $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}

		return $this->fileData;
	}

	/**
	 * 
	 * @param HeadedCsvReader $csv
	 * @throws OutOfBoundsException
	 */
	protected function parseLine( HeadedCsvReader $csv ) {
		$type = $csv->currentCol( 'TransactionType' );

		// Only process captures
		if ( $type !== 'Capture' ) {
			return;
		}

		$msg = array();
		$orderId = $csv->currentCol( 'SellerReferenceId' );
		$parts = explode( '-', $orderId );
		$msg['contribution_tracking_id'] = $parts[0];
		$msg['currency'] = $csv->currentCol( 'CurrencyCode' );
		$msg['date'] = UtcDate::getUtcTimestamp(
			$csv->currentCol( 'TransactionPostedDate' )
		);
		$msg['fee'] = -1 * $csv->currentCol( 'TotalTransactionFee' );
		$msg['gateway'] = 'amazon';
		$msg['gateway_txn_id'] = $csv->currentCol( 'AmazonTransactionId' );
		$msg['gross'] = $csv->currentCol( 'TransactionAmount' );
		$msg['log_id'] = $csv->currentCol( 'SellerReferenceId' );
		$msg['payment_method'] = 'amazon';

		$this->fileData[] = $msg;
	}
}
