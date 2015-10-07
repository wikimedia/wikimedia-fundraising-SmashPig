<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

use SmashPig\Core\DataFiles\DataFileException;
use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\UtcDate;

/**
 * Parses settlement reports from Amazon MWS
 * http://amazonpayments.s3.amazonaws.com/documents/Sample%20Settlement%20Report.pdf
 */
class AuditParser {

	protected $processedTypes = array(
		'Capture',
		'Refund',
		'Chargeback',
	);

	protected $fileData = array();

	public function parseFile( $path ) {
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

		// Only process certain types of transaction
		if ( !in_array( $type, $this->processedTypes ) ) {
			return;
		}

		$msg = array();

		// Common to all types
		$msg['date'] = UtcDate::getUtcTimestamp(
			$csv->currentCol( 'TransactionPostedDate' )
		);
		$msg['gateway'] = 'amazon';
		$msg['gross'] = $csv->currentCol( 'TransactionAmount' );

		switch( $type ) {
			case 'Capture':
				$this->parseDonation( $csv, $msg );
				break;
			case 'Refund':
			case 'Chargeback':
				$this->parseRefund( $csv, $msg, $type );
				break;
		}

		$this->fileData[] = $msg;
	}

	protected function parseRefund( HeadedCsvReader $csv, array &$msg, $type ) {
		// We need to set this when refunding.  TODO: figure out chargebacks!
		$msg['gateway_parent_id'] = $csv->currentCol( 'SellerReferenceId' );
		$msg['gateway_refund_id'] = 'RFD ' . $csv->currentCol( 'AmazonTransactionId' );
		$msg['gross_currency'] = $csv->currentCol( 'CurrencyCode' );
		$msg['type'] = strtolower( $type );
	}

	protected function parseDonation( HeadedCsvReader $csv, array &$msg ) {
		$msg['contribution_tracking_id'] = $this->getContributionTrackingId( $csv );
		$msg['currency'] = $csv->currentCol( 'CurrencyCode' );
		// Fees are listed as negative amounts in audit files
		$msg['fee'] = -1 * $csv->currentCol( 'TotalTransactionFee' );
		$msg['gateway_txn_id'] = $csv->currentCol( 'AmazonTransactionId' );
		$msg['log_id'] = $csv->currentCol( 'SellerReferenceId' );
		$msg['payment_method'] = 'amazon';
	}

	protected function getContributionTrackingId( $csv ) {
		$orderId = $csv->currentCol( 'SellerReferenceId' );
		$parts = explode( '-', $orderId );
		return $parts[0];
	}
}
