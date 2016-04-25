<?php
namespace SmashPig\PaymentProviders\Worldpay\Audit;

use \Exception;

use SmashPig\Core\Logging\Logger;

class WpgReconciliationFile {

	const FILENAME_PATTERN = '/WPG_AUDIT/';

	protected $recordTypes;
	protected $fileData = array();

	function __construct() {
		$this->columnHeaders = array(
			'OrderCode',
			'Date',
			'PaymentMethod',
			'Status',
			'CurrencyCode',
			'Amount',
			'Commission',
			'Batch ID',
		);
	}

	static function isMine( $path ) {
		$filename = basename( $path );
		return preg_match( self::FILENAME_PATTERN, $filename );
	}

	function parse( $path ) {
		$this->path = $path;
		$this->file = fopen( $path, 'r' );

		$ignoreLines = 1;
		for ( $i = 0; $i < $ignoreLines; $i++ ) {
			fgets( $this->file );
		}

		while ( $line = fgetcsv( $this->file, 0, "\t" ) ) {
			// "A blank line in a CSV file will be returned as an array compri-
			// sing a single null field, and will not be treated as an error."
			if ( count( $line ) === 1 && is_null( $line[0] ) ) {
				continue;
			}
			try {
				$this->parseLine( $line );
			} catch ( JunkRowException $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}
		fclose( $this->file );
		return $this->fileData;
	}

	/**
	 * Parse one line.
	 */
	protected function parseLine( $line ) {
		$row = array_combine( $this->columnHeaders, $line );
		$msg = $this->normalize( $row );
		if ( $msg ) {
			$this->fileData[] = $msg;
		}
	}

	/**
	 * Normalize the pieces of the message that exist, according to the
	 * definition of a standard WMF queue message.
	 *
	 * Defaults should always be left up to the relevant queue consumer.
	 *
	 * See https://wikitech.wikimedia.org/wiki/Fundraising/Queue_messages
	 */
	protected function normalize( $record ) {
		$msg = array();

		switch ( $record['Status'] ) {
			case 'SENT_FOR_AUTHORISATION':
			case 'SETTLED':
			case 'AUTHORISED':
			case 'REFUSED':
			case 'SENT_FOR_REFUND':
			case 'EXPIRED':
				return null;
			case 'CAPTURED':
				$queue = 'donations';
				break;
			case 'REFUNDED':
			case 'CANCELLED':
			case 'CHARGED_BACK':
				$queue = 'refund';
				break;
			default:
				throw new JunkRowException( "Unknown transaction type: " . $record['Status'] );
		}

		$msg['date'] = strtotime( $record['Date'] . ' UTC' );
		$msg['gateway'] = 'worldpay';
		$msg['gross'] = $record['Amount'];

		if ( $queue === 'refund' ) {
			$msg['gross_currency'] = $record['CurrencyCode'];
			$msg['gateway_parent_id'] = $record['OrderCode'];
			$msg['gateway_refund_id'] = $record['OrderCode'];

			$msg['type'] = 'refund';
			if ( $record['Status'] == 'CHARGED_BACK' ) {
				$msg['type'] = 'chargeback';
			} elseif ( $record['Status'] == 'CANCELLED' ) {
				$msg['type'] = 'cancel';
			}

			return $msg;
		}

		$msg['gateway_txn_id'] = $record['OrderCode'];
		$msg['currency'] = $record['CurrencyCode'];
		// this one is okay, because WP only does cc at this point. Maybe forever?
		$msg['payment_method'] = 'cc';
		$msg['payment_submethod'] = $this->lookupCardType( $record['PaymentMethod'] );

		return $msg;
	}

	protected function lookupCardType( $rawType ) {
		$mapping = array(
			'CARTEBLEUE-SSL' => 'cb',
			'CB-SSL' => 'mc', // XXX this means visa or MC, no way to tell
			'AMEX-SSL' => 'amex',
		);

		if ( array_key_exists( $rawType, $mapping ) ) {
			return $mapping[$rawType];
		}

		Logger::warning( "Unknown card type [{$rawType}]" );
		return $rawType;
	}
}

class JunkRowException extends Exception {
}
