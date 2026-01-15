<?php namespace SmashPig\PaymentProviders\dlocal\Audit;

use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;

class DlocalAudit implements AuditParser {

	protected array $columnHeaders = [
		'Type', // 'Payment' or 'Refund'
		'Creation date', // YYYY-MM-dd HH:mm:ss
		'Settlement date', // same format
		'Reference', // gateway_txn_id
		'Invoice', // ct_id.attempt_num
		'Country',
		'Payment Method', // corresponds to our payment_submethod
		'Payment Method Type', // our payment_method
		'Net Amount (local)',
		'Amount (USD)', // gross, including fee
		'currency', // yup, this one is lower case
		'Status',
		'User Mail',
		// These two fields refer to the original donation for refunds
		'Transaction Reference',
		'Transaction Invoice',
		'Fee', // In USD.  Dlocal's processing fee
		'IOF', // In USD.  Fee for financial transactions in Brazil
		// The IOF is included in Dlocal's fee, but broken out by request
	];

	protected array $ignoredStatuses = [
		'Cancelled', // User pressed cancel or async payment expired
		'In process', // Chargeback is... charging back? 'Settled' means done
		'Reimbursed', // Chargeback settled in our favor - not refunding
		'Waiting Details', // Refund is in limbo; we'll wait for 'Completed'
	];

	protected $fileData;

	private array $headerRow = [];

	public function parseFile( string $path ): array {
		$this->fileData = [];
		$file = fopen( $path, 'r' );

		$ignoreLines = 1;
		for ( $i = 0; $i < $ignoreLines; $i++ ) {
			fgets( $file );
		}

		while ( $line = fgetcsv( $file, 0, ';', '"', '\\' ) ) {
			try {
				$this->parseLine( $line );
			} catch ( NormalizationException $ex ) {
				// TODO: actually throw these below
				Logger::error( $ex->getMessage() );
			}
		}
		fclose( $file );

		return $this->fileData;
	}

	protected function parseLine( array $line ): void {
		$row = array_combine( $this->columnHeaders, $line );

		// Ignore certain statuses
		if ( in_array( $row['Status'], $this->ignoredStatuses ) ) {
			return;
		}

		$parser = $this->getParser( $row );

		$line = $parser->parse();
		if ( $line ) {
			$this->fileData[] = $line;
		}
	}

	/**
	 * @param array $row
	 *
	 * @return \SmashPig\PaymentProviders\dlocal\Audit\ReportFileParser|\SmashPig\PaymentProviders\dlocal\Audit\SettlementFileParser
	 */
	public function getParser( array $row ): BaseParser|ReportFileParser {
		$parser = new ReportFileParser( $row, $this->headerRow );
		return $parser;
	}

}
