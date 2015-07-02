<?php namespace SmashPig\PaymentProviders\Astropay\Audit;

use OutOfBoundsException;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\Astropay\ReferenceData;

class AstropayAudit {

	protected $columnHeaders;
	protected $ignoredStatuses;

	public function __construct() {
		// FIXME: where the heck is currency code?
		$this->columnHeaders = array(
			'Type', // 'Payment' or 'Refund'
			'Creation date', // YYYY-MM-dd HH:mm:ss
			'Settlement date', // same format
			'Reference', // gateway_trxn_id
			'Invoice', // ct_id.attempt_num
			'Country',
			'Payment Method', // corresponds to our payment_submethod
			'Payment Method Type', // our payment_method
			'Net Amount (local)',
			'Amount (USD)', // gross, including fee
			'Status',
			'User Mail',
			// These two fields refer to the original donation for refunds
			'Transaction Reference',
			'Transaction Invoice',
			'Fee', // In USD.  Astropay's processing fee
			'IOF', // In USD.  Fee for financial transactions in Brazil
			// The IOF is included in Astropay's fee, but broken out by request
		);
		// We don't need do anything with some audit lines
		$this->ignoredStatuses = array(
			'Cancelled', // User pressed cancel or async payment expired
			'In process', // Chargeback is... charging back? 'Settled' means done
			'Reimbursed', // Chargeback settled in our favor - not refunding
			'Waiting Details', // Refund is in limbo; we'll wait for 'Completed'
		);
	}

	public function parseFile( $path ) {
		$this->path = $path;
		$this->file = fopen( $path, 'r' );

		$ignoreLines = 1;
		for ( $i = 0; $i < $ignoreLines; $i++ ) {
			fgets( $this->file );
		}

		while ( $line = fgetcsv( $this->file, 0, ';', '"', '\\' ) ) {
			try {
				$this->parseLine( $line );
			} catch ( NormalizationException $ex ) {
				// TODO: actually throw these below
				Logger::error( $ex->getMessage() );
			}
		}
		fclose( $this->file );

		return $this->fileData;
	}

	protected function parseLine( $line ) {
		$row = array_combine( $this->columnHeaders, $line );

		// Ignore certain statuses
		if ( in_array( $row['Status'], $this->ignoredStatuses ) ) {
			return;
		}

		$msg = array();

		// Common to all types
		$msg['date'] = UtcDate::getUtcTimestamp( $row['Creation date'] );
		$msg['gateway'] = 'astropay';
		$msg['gross'] = $row['Net Amount (local)'];

		switch( $row['Type'] ) {
			case 'Payment':
				$this->parseDonation( $row, $msg );
				break;
			case 'Refund':
			case 'Chargeback':
				$this->parseRefund( $row, $msg );
				break;
			default:
				throw new OutOfBoundsException( "Unknown audit line type {$row['Type']}." );
		}

		$this->fileData[] = $msg;
	}

	protected function parseRefund( array $row, array &$msg ) {
		$msg['gateway_parent_id'] = $row['Transaction Reference'];
		$msg['gateway_refund_id'] = 'RFD ' . $row['Reference'];
		$msg['gross_currency'] = 'BRL'; // FIXME when AP adds this column!
		$msg['type'] = strtolower( $row['Type'] );
	}

	protected function parseDonation( array $row, array &$msg ) {
		$parts = explode( '.', $row['Invoice'] );
		$msg['contribution_tracking_id'] = $parts[0];
		$msg['country'] = $row['Country'];
		$msg['currency'] = 'BRL'; // FIXME when AP adds this column!
		$msg['email'] = $row['User Mail'];
		$msg['settled_fee'] = $row['Fee']; // settled_fee since it's given in USD
		$msg['gateway_txn_id'] = $row['Reference'];
		$msg['payment_method'] = ReferenceData::decodePaymentMethod( $row['Payment Method Type'] );
		$msg['payment_submethod'] = ReferenceData::decodePaymentSubmethod( $row['Payment Method'] );
		if ( $row['Settlement date'] ) {
			$msg['settled_date'] = UtcDate::getUtcTimestamp( $row['Settlement date'] );
		}
		if ( $row['Amount (USD)'] ) {
			$msg['settled_currency'] = 'USD';
			$msg['settled_gross'] = $row['Amount (USD)'];
		}
	}
}
