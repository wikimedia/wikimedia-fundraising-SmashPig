<?php

namespace SmashPig\PaymentProviders\Worldpay\Audit;

require_once "ReferenceData.php";

/**
 * See EMIS User Guide V9 January 2014.pdf
 */
class TransactionReconciliationFile {

	const filenamePattern = '/^MA\.PISCESSW\.#M\.RECON\..*/';

	protected $recordTypes;
	protected $fileData = array( );

	function __construct() {
		$this->row_header_segment = array(
			array( "record_type", 2 ),
			array( "sequence_no", 8 ),
		);

		$credit_debit_summary_segment = array(
			array( "accepted_debits", 11 ),
			array( "rejected_debits", 11 ),
			array( "pending_debits", 11 ),
			array( "accepted_credits", 11 ),
			array( "rejected_credits", 11 ),
			array( "pending_credits", 11 ),
			array( "accepted_debits_count", 7 ),
			array( "rejected_debits_count", 7 ),
			array( "pending_debits_count", 7 ),
			array( "accepted_credits_count", 7 ),
			array( "rejected_credits_count", 7 ),
			array( "pending_credits_count", 7 ),
		);

		$file_header = new FixedLengthRecordSpecification( "00", array(
			array(
				array( "record_type", 2 ),
				array( "sequence_no", 8 ),
				array( "file_id", 13 ),
				array( "count", 7 ),
			),
			$credit_debit_summary_segment,
			array(
				array( "file_creation_date", 6 ),
				array( "file_sequence_number", 3 ),
				array( "site_id", 3 ),
			)
			) );

		$reconciliation_merchant_company_header = new FixedLengthRecordSpecification( "05", array(
			array(
				array( "record_type", 2 ),
				array( "sequence_no", 8 ),
				array( "company_number", 13 ),
				array( "count", 7 ),
			),
			$credit_debit_summary_segment
			) );

		$reconciliation_merchant_outlet = new FixedLengthRecordSpecification( "10", array(
			array(
				array( "record_type", 2 ),
				array( "sequence_no", 8 ),
				array( "merchant_id", 13 ),
				array( "trading_day", 6 ),
				array( "processing_date", 6 ),
			),
			$credit_debit_summary_segment
			) );

		$this->reconciliation_transaction_data = new FixedLengthRecordSpecification( "15", array( array(
				array( "record_type", 2 ),
				array( "sequence_no", 8 ),
				array( "pan", 19 ),
				array( "expiry_date", 4 ),
				array( "transaction_value", 11 ),
				array( "transaction_date", 6 ),
				array( "transaction_time", 6 ),
				array( "transaction_type", 1 ),
				array( "transaction_source", 1 ),
				array( "receipt_number", 6 ),
				array( "status", 1 ),
				array( "reserved1", 2 ),
				array( "local_value", 9 ),
				array( "local_currency_code", 3 ),
				array( "local_exponent", 1 ),
				array( "settlement_value", 9 ),
				array( "settlement_currency_code", 3 ),
				array( "settlement_exponent", 1 ),
				array( "acquired_processed_indicator", 1 ),
				array( "card_type", 5 ),
			) ) );

		$this->reconciliation_transaction_supplementary_data = new FixedLengthRecordSpecification( "16", array( array(
				array( "record_type", 2 ),
				array( "sequence_no", 8 ),
				array( "auth_code", 6 ),
				array( "auth_method", 1 ),
				array( "card_issue_number", 2 ),
				array( "card_start_date", 4 ),
				array( "cash_amount", 7 ),
				array( "originators_transaction_reference", 20 ),
				array( "ticket_number", 14 ),
			) ) );

		$this->recordTypes = array(
			$file_header,
			$reconciliation_merchant_company_header,
			$reconciliation_merchant_outlet,
			$this->reconciliation_transaction_data,
			$this->reconciliation_transaction_supplementary_data,
		);
	}

	static function isMine( $path ) {
		$filename = basename( $path );
		return preg_match( self::filenamePattern, $filename );
	}

	function parse( $path ) {
		$this->pending_data = null;
		$this->pending_supplemental_data = null;

		$this->path = $path;
		$this->file = fopen( $path, 'r' );

		while ( $line = fgets( $this->file ) ) {
			$this->parse_line( $line );
		}
		fclose( $this->file );

		$this->flush_data();
		return $this->fileData;
	}

	/**
	 * Parse one line.
	 *
	 * There is a crazy thing happening here where we need to coordinate
	 * sequential lines, and merge supplemental records into the main transaction
	 * data.  See add_transaction_data and add_supplementary_data.
	 */
	protected function parse_line( $line ) {
		# Peek at row header to determine its type
		$row_info = FixedLengthRecordParser::parse( $this->row_header_segment, substr( $line, 0, 10 ) );

		# Find the corresponding line format and parse the contents
		$record = null;
		foreach ( $this->recordTypes as $type ) {
			if ( $type->getPrefix() === $row_info['record_type'] ) {
				$record = FixedLengthRecordParser::parse( $type->fields, $line );
			}
		}

		if ( !$record ) {
			// FIXME: correct Exception type, and get context (path) from log context
			// SUPERFIXME: This introduces an extreme degree of fragility into the system. One weird line can't blow up the whole thing; We'd get killed by the schrapnel on the daily.
//			throw new \Exception( "Unknown record type {$row_info['record_type']} while processing {$this->path}, aborting!" );
			//for now...
			error_log( "Unknown record type {$row_info['record_type']} while processing {$this->path}. Rolling over it." );
		}

		# Dispatch to a handler
		$record_type = false;
		if ( array_key_exists( 'record_type', $record ) ) {
			$record_type = $record['record_type'];
		}
		if ( $record_type === $this->reconciliation_transaction_data->getPrefix() ) {
			$this->add_transaction_data( $record );
		} elseif ( $record_type === $this->reconciliation_transaction_supplementary_data->getPrefix() ) {
			$this->add_supplementary_data( $record );
		} else {
			# ignore other crap.
			# TODO: assertions for checksummy things built into the file
		}
	}

	protected function add_transaction_data( $record ) {
		$this->flush_data();
		$this->pending_data = $record;
	}

	protected function add_supplementary_data( $record ) {
		if ( !$this->pending_data ) {
			// TODO: get line number from the context
			throw new \Exception( "Cannot eat supplementary transaction data because there no unconsumed base data. Line {$record["sequence_no"]}" );
		}

		if ( $this->pending_supplemental_data ) {
			throw new \Exception( "Cannot eat supplementary data because there is already unconsumed supplemental data. Line {$record["sequence_no"]}" );
		}

		$this->pending_supplemental_data = $record;
		$this->flush_data();
	}

	protected function flush_data() {
		if ( $this->pending_data ) {
			$this->send_transaction();
		}
	}

	protected function send_transaction() {
		$record = $this->pending_data;

		# Verify that the data and supplemental data are a pair
		if ( $this->pending_supplemental_data ) {
			if ( ( int ) ($this->pending_supplemental_data['sequence_no']) !== ( $this->pending_data['sequence_no'] + 1 ) ) {
				throw new \Exception( "Mismatched data and supplemental data! " . print_r( $this->pending_supplemental_data, true ) . " " . print_r( $this->pending_data, true ) );
			}
			$record = array_merge( $record, $this->pending_supplemental_data );
		}

		$msg = $this->normalize( $record );

		$this->pending_data = null;
		$this->pending_supplemental_data = null;

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
		$msg = array( );

		if ( $record["transaction_type"] == "0" ) {
			$queue = "donations";
		} elseif ( $record["transaction_type"] == "5" ) {
			$queue = "refund";
		} else {
			//FIXME: This also seems incredibly fragile.
			throw new \Exception( "Don't know how to handle transaction type {$record["transaction_type"]}" );
		}

		//ARGH.
		//transaction_date looks like ddmmyy.
		//@TODO: Verify that the transaction_time is in fact UTC, and not some other weirdo country time.
		$datestring = '20' . substr( $record["transaction_date"], 4, 2 ) . substr( $record["transaction_date"], 2, 2 ) . substr( $record["transaction_date"], 0, 2 );
		$datestring .= ' ' . $record["transaction_time"] . '-0000';

		$msg["date"] = strtotime( $datestring );

		$msg["gateway"] = "worldpay";

		if ( array_key_exists( "originators_transaction_reference", $record ) ) {
			$msg["gateway_txn_id"] = trim( $record["originators_transaction_reference"] );
		} else {
			throw new \Exception( "We're gonna die: no gateway_txn_id available." );
		}

		# The default currency is GBP, don't make me explain why the amount
		# comes from a different field when currency != GBP :(
		if ( trim( $record["local_currency_code"] ) ) {
			$msg["currency"] = $record["local_currency_code"];
			$msg["gross"] = ( int ) ( $record["local_value"] ) * self::exponent_to_multiplier( $record["local_exponent"] );
		} else {
			$msg["currency"] = "GBP";
			$msg["gross"] = ( int ) ( $record["transaction_value"] ) * self::exponent_to_multiplier( 2 );
		}

		if ( $queue === "refund" ) {
			$msg["gross_currency"] = $msg["currency"];
			$msg["gateway_parent_id"] = $msg["gateway_txn_id"];
			# Note that we do not have a new txn id for the refund
			$msg["gateway_refund_id"] = $msg["gateway_txn_id"];
			# FIXME: chargeback vs refund info is not available in this file.
			$msg["type"] = "refund";
			#log.info("+Sending\t{id}\t{date}\t{type}".format(id=msg["gateway_parent_id"], date=iso_date, type=msg["type"]))
			#self.send(queue, msg)
			return $msg;
		}

		/*
		  if self.crm.transaction_exists(gateway_txn_id=msg["gateway_txn_id"], gateway="worldpay"):
		  log.info("-Duplicate\t{id}\t{date}\t{type}".format(id=msg["gateway_txn_id"], date=iso_date, type=queue))
		  return
		 */

		# Switch behavior depending on the status.  We only like "accepted" transactions.
		$status = trim( $record["status"] );
		if ( $status == "P" ) {
			#log.info("-Pending\t{id}\t{date}\t{type}".format(id=msg["gateway_txn_id"], date=iso_date, type=queue))
			return;
		} elseif ( $status == "R" ) {
			#log.info("-Rejection\t{id}\t{date}\t{type}".format(id=msg["gateway_txn_id"], date=iso_date, type=queue))
			return;
		} elseif ( $status != "A" ) {
			throw new \Exception( "Unknown gateway status: {$status}" );
		}

		# Include settlement details if they are available.
		if ( trim( $record["settlement_value"] ) ) {
			if ( trim( $record["settlement_currency_code"] ) ) {
				$msg["settlement_currency"] = $record["settlement_currency_code"];
			} else {
				$msg["settlement_currency"] = "GBP";
			}
			$msg["settlement_amount"] = ( int ) ( $record["settlement_value"] ) * self::exponent_to_multiplier( $record["settlement_exponent"] );
		}

		$msg["payment_method"] = "cc"; //this one is okay, because WP only does cc at this point. Maybe forever?
		$msg["payment_submethod"] = ReferenceData::decode_card_type( trim( $record["card_type"] ) );

		# custom values
		$msg["raw_card_type"] = trim( $record["card_type"] );

		return $msg;
	}

	/**
	 * Convert an exponent to a multiplier
	 *
	 * The exponent defines how many digits are "minor units" of the currency, so USD
	 * for example has an exponent of 2.  Our queue consumer assumes that amounts are
	 * always given in major units, so we produce a multiplier here which will
	 * convert from the Worldpay amount, formatted in minor units, to the expected amount.
	 *
	 * For example, a currency with 3 digits of minor units should be multipled by 0.001
	 * before sending over the queue.
	 */
	static function exponent_to_multiplier( $exponent ) {
		if ( !trim( $exponent ) ) {
			# The default is GBP, which have an exponent of 2.
			$exponent = 2;
		}

		return pow( 10, 0 - ( int ) ( $exponent ) );
	}

}

class FixedLengthRecordSpecification {

	public $prefix;
	public $fields = array( );

	function __construct( $prefix, $fieldSegments ) {
		$this->prefix = $prefix;

		foreach ( $fieldSegments as $segment ) {
			$this->fields = array_merge( $this->fields, $segment );
		}
	}

	public function getPrefix() {
		return $this->prefix;
	}

}

/**
 * FIXME: not compatible with Wr1 field specs, yet
 */
class FixedLengthRecordParser {

	static function parse( $fields, $line ) {
		$pos = 0;
		$out = array( );
		foreach ( $fields as $field ) {
			list( $fieldName, $length ) = $field;
			$out[$fieldName] = substr( $line, $pos, $length );
			$pos += $length;
		}
		$pos = 0;
		return $out;
	}

}
