<?php
namespace SmashPig\PaymentProviders\Worldpay\Audit;

use \Exception;

use SmashPig\Core\Logging\Logger;

/**
 * See Transaction Detail Version 2 US Report.doc
 */
class LynkReconciliationFile {

	const filenamePattern = '/^TranDetVer2_.*/';

	protected $recordTypes;
	protected $fileData = array();

	function __construct() {
		$this->columnHeaders = array(
			'Record Type',
			// TODO: turns out this number is not actually unique, we are getting final zeroes
			'Reference Number',
			// TODO: This is documented but doesn't exist?
			// 'Lowest Defined User field',
			'Merchant Number',
			'Terminal ID',
			'Process Date',
			'Batch Number',
			'Sequence Number',
			'Card Network Type',
			'Transaction Type',
			// This is rounded to the nearest *day*.
			'Transaction Date Time',
			'Authorization Only',
			// Seems to be our best approximation of donation time.
			// TODO: I'm randomly and irresponsibly guessing this is in UTC.
			'Authorization Date Time',
			// Empty.
			'Card Payment Date',
			'Approval Code',
			'Settled Amount',
			'Dispensed Amount',
			'Cash Back Amount',
			'Surcharge Amount',
			'Original Authorized Amt',
			'Total Authorized Amount',
			'Complete Code',
			'Card Number',
			'Expiration Date',
			'Card Merchant Number',
			'Switch Network',
			'Authorized Network',
			'Authorized Code',
			'Authorized Type',
			'Authorized Response Code',
			'Response ACI',
			'Authorized Source Code',
			'VISA Trans Ref Num',
			'MC Banknet Data  ',
			'Validation Code',
			'AVS Response Code',
			'Capture Type',
			'POS Entry Mode',
			'POS Term Capability',
			'Card ID Method',
			'Industry Type',
			'Restaurant Server ID',
			'Restaurant Tip Amt',
			'Lodge Folio Number  ',
			'Lodge Arrival Date',
			'Lodge Departure Date',
			'Lodge No Show',
			'Lodge Stay Duration',
			'Lodge Local Phone  ',
			'Lodge Customer Service Phone',
			'Lodge Charge Description',
			'Lodge Room Rate',
			'Lodge Prestige Prop Indicator',
			'Merchant Order Number',
		);
	}

	static function isMine( $path ) {
		$filename = basename( $path );
		return preg_match( self::filenamePattern, $filename );
	}

	function parse( $path ) {
		$this->path = $path;
		$this->file = fopen( $path, 'r' );

		$ignoreLines = 2;
		for ( $i = 0; $i < $ignoreLines; $i++ ) {
			fgets( $this->file );
		}

		while ( $line = fgetcsv( $this->file, 0, ',', '"', '\\' ) ) {
			try {
				$this->parseLine( $line );
			} catch ( NormalizationException $ex ) {
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
		if ( $line[0] === '3' ) {
			// Something like end-of-file.  Undocumented, but expected.
			return;
		}

		// Truncate row, cos we get differing numbers of zero columns after the
		// known fields.
		while ( count( $line ) > count( $this->columnHeaders ) ) {
			array_pop( $line );
		}
		$row = array_combine( $this->columnHeaders, $line );

		$msg = $this->normalize( $row );
		$this->fileData[] = $msg;
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

		switch ( $record['Transaction Type'] ) {
		case 'Credit Sale':
		case 'Credit Ticket':
			$queue = 'donations';
			break;
		case 'Credit Return':
		case 'Credit Refund':
			$queue = 'refund';
			break;
		default:
			throw new NormalizationException( "Unknown transaction type: " . $record['Transaction Type'] );
		}

		$msg['date'] = strtotime( $record['Authorization Date Time'] . ' UTC' );

		$msg['gateway'] = 'worldpay';

		// FIXME: Do we want to do anything about the missing '.0'?
		$msg['gateway_txn_id'] = $record['Merchant Order Number'];

		$msg['currency'] = 'USD';
		$msg['gross'] = $record['Settled Amount'];

		if ( $queue === 'refund' ) {
			$msg['gross_currency'] = $msg['currency'];
			$msg['gateway_parent_id'] = $msg['gateway_txn_id'];
			# Note that we do not have a new txn id for the refund
			$msg['gateway_refund_id'] = $msg['gateway_txn_id'];
			# FIXME: chargeback vs refund info is not available in this file.
			$msg['type'] = 'refund';
			return $msg;
		}

		$msg['payment_method'] = 'cc'; // this one is okay, because WP only does cc at this point. Maybe forever?
		$msg['payment_submethod'] = $this->lookupCardType( $record['Card Network Type'] );

		return $msg;
	}

	protected function lookupCardType( $rawType ) {
		$mapping = array(
			'Visa' => 'visa',
			'MasterCard' => 'mc',
			'American Express NS' => 'amex',
			'Discover' => 'discover',
		);

		if ( array_key_exists( $rawType, $mapping ) ) {
			return $mapping[$rawType];
		}

		Logger::warning( "Unknown card type [{$rawType}]" );
		return $rawType;
	}
}

class NormalizationException extends Exception {
}
