<?php
namespace SmashPig\PaymentProviders\Worldpay\Audit;

use \Exception;

use SmashPig\Core\Logging\Logger;

class WpgReconciliationFile {

	const filenamePattern = '/WPG_AUDIT/';

	protected $recordTypes;
	protected $fileData = array( );

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
		return preg_match( self::filenamePattern, $filename );
	}

	function parse( $path ) {
		$this->path = $path;
		$this->file = fopen( $path, 'r' );

		$ignoreLines = 1;
		for ( $i = 0; $i < $ignoreLines; $i++ ) {
			fgets( $this->file );
		}

		while ( $line = fgetcsv( $this->file, 0, "\t" ) ) {
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
				return null;
			case 'CAPTURED':
				$queue = 'donations';
				break;
			case 'REFUNDED':
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
			$msg['gateway_refund_id'] = $record['OrderCode'] . 'R';
			$msg['type'] = 'refund';
			return $msg;
		}

		$msg['gateway_txn_id'] = $record['OrderCode'];
		$msg['currency'] = $record['CurrencyCode'];
		$msg['payment_method'] = 'cc'; //this one is okay, because WP only does cc at this point. Maybe forever?
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

class JunkRowException extends Exception {}
