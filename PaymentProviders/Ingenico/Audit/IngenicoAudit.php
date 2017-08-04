<?php namespace SmashPig\PaymentProviders\Ingenico\Audit;

use DOMDocument;
use DOMElement;
use RuntimeException;
use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\Ingenico\ReferenceData;

class IngenicoAudit implements AuditParser {

	protected $fileData;

	protected $donationMap = array(
		'PaymentAmount' => 'gross',
		'IPAddressCustomer' => 'user_ip',
		'BillingFirstname' => 'first_name',
		'BillingSurname' => 'last_name',
		'BillingStreet' => 'street_address',
		'BillingCity' => 'city',
		'ZipCode' => 'postal_code',
		'BillingCountryCode' => 'country',
		'BillingEmail' => 'email',
		'AdditionalReference' => 'contribution_tracking_id',
		'PaymentProductId' => 'gc_product_id',
		'OrderID' => 'order_id',
		// Ingenico recurring donations all have the same OrderID
		// We can only tell them apart by the EffortID, which we
		// might as well normalize to 'installment'.
		'EffortID' => 'installment',
		'PaymentCurrency' => 'currency',
		'AmountLocal' => 'gross',
		'TransactionDateTime' => 'date',
	);

	protected $refundMap = array(
		'DebitedAmount' => 'gross',
		'AdditionalReference' => 'contribution_tracking_id',
		'OrderID' => 'gateway_parent_id',
		'EffortID' => 'installment',
		'DebitedCurrency' => 'gross_currency',
		'TransactionDateTime' => 'date',
	);

	protected $recordsWeCanDealWith = array(
		// Credit card item that has been processed, but not settled.
		// We take these seriously.
		// TODO: Why aren't we waiting for +ON (settled)?
		'XON' => 'donation',
		// Settled "Invoice Payment". Could be invoice, bt, rtbt, check,
		// prepaid card, ew, cash
		'+IP' => 'donation',
		'-CB' => 'chargeback', // Credit card chargeback
		'-CR' => 'refund', // Credit card refund
		'+AP' => 'donation', // Direct Debit collected
	);

	public function parseFile( $path ) {
		$this->fileData = array();
		$unzippedFullPath = $this->getUnzippedFile( $path );

		// load the XML into a DOMDocument.
		// Total Memory Hog Alert. Handle with care.
		$domDoc = new DOMDocument( '1.0' );
		Logger::info( "Loading XML from $unzippedFullPath" );
		$domDoc->load( $unzippedFullPath );
		unlink( $unzippedFullPath );
		Logger::info( "Processing" );

		foreach ( $domDoc->getElementsByTagName( 'DataRecord' ) as $recordNode ) {
			$this->parseRecord( $recordNode );
		}

		return $this->fileData;
	}

	protected function parseRecord( DOMElement $recordNode ) {
		$category = $recordNode->getElementsByTagName( 'Recordcategory' )
			->item( 0 )->nodeValue;
		$type = $recordNode->getElementsByTagName( 'Recordtype' )
			->item( 0 )->nodeValue;

		$compoundType = $category . $type;
		if ( !array_key_exists( $compoundType, $this->recordsWeCanDealWith ) ) {
			return;
		}

		if ( $category === '-' ) {
			$refundType = $this->recordsWeCanDealWith[$compoundType];
			$record = $this->parseRefund( $recordNode, $refundType );
		} else {
			$record = $this->parseDonation( $recordNode );
		}
		$record = $this->normalizeValues( $record );
		// TODO: label Connect API donations as 'ingenico'
		$record['gateway'] = 'globalcollect';

		$this->fileData[] = $record;
	}

	protected function parseDonation( DOMElement $recordNode ) {
		$record = $this->xmlToArray( $recordNode, $this->donationMap );
		$record['gateway_txn_id'] = $record['order_id'];
		$record = $this->addPaymentMethod( $record );
		if ( $record['installment'] > 1 ) {
			$record['recurring'] = 1;
			// If $record['installment'] == 1, we may have a normal one-time
			// payment, or the first payment of a recurring donation.
			// This logic is sufficient for WMF's purposes, because we're only
			// using the 'recurring' flag parsed out of the audit file to make
			// sure donations after the first one are correctly inserted rather
			// than dropped as duplicates of the first donation.
			// We'll determine the recurring-ness of donations where
			// installment=1 when we parse our logs looking for the order type.
		}
		return $record;
	}

	protected function parseRefund( DOMElement $recordNode, $type ) {
		$record = $this->xmlToArray( $recordNode, $this->refundMap );
		$record['type'] = $type;
		if ( $record['installment'] < 0 ) {
			// Refunds have negative EffortID. Weird.
			// TODO: for refunds of recurring payments, determine whether the
			// refund's EffortID is always the negative of the corresponding
			// installment's EffortID. We want to know which one we refunded.
			$record['installment'] = $record['installment'] * -1;
		}
		return $record;
	}

	protected function xmlToArray( DOMElement $recordNode, $map ) {
		$record = array();
		foreach ( $map as $theirs => $ours ) {
			foreach ( $recordNode->getElementsByTagName( $theirs ) as $recordItem ) {
				$record[$ours] = $recordItem->nodeValue;  // there 'ya go: Normal already.
			}
		}
		return $record;
	}

	/**
	 * Adds our normalized payment_method and payment_submethod params based
	 * on the codes that GC uses
	 *
	 * @param array $record The record from the wx file, in array format
	 * @return array The $record param with our normal keys appended
	 */
	function addPaymentMethod( $record ) {
		$normalized = ReferenceData::decodePaymentMethod(
			$record['gc_product_id']
		);
		$record = array_merge( $record, $normalized );

		unset ( $record['gc_product_id'] );
		return $record;
	}

	/**
	 * @param string $path Path to original zipped file
	 * @return string Path to unzipped file in working directory
	 */
	protected function getUnzippedFile( $path ) {
		$zippedParts = explode( DIRECTORY_SEPARATOR, $path );
		$zippedFilename = array_pop( $zippedParts );
		// TODO keep unzipped files around?
		$workingDirectory = tempnam( sys_get_temp_dir(), 'ingenico_audit' );
		if ( file_exists( $workingDirectory ) ) {
			unlink( $workingDirectory );
		}
		mkdir( $workingDirectory );
		// whack the .gz on the end
		$unzippedFilename = substr( $zippedFilename, 0, strlen( $zippedFilename ) - 3 );

		$copiedZipPath = $workingDirectory . DIRECTORY_SEPARATOR . $zippedFilename;
		copy( $path, $copiedZipPath );
		if ( !file_exists( $copiedZipPath ) ) {
			throw new RuntimeException(
				"FILE PROBLEM: Trying to copy $path to $copiedZipPath " .
				'for decompression, and something went wrong'
			);
		}

		$unzippedFullPath = $workingDirectory . DIRECTORY_SEPARATOR . $unzippedFilename;
		// decompress
		Logger::info( "Gunzipping $copiedZipPath" );
		// FIXME portability
		$cmd = "gunzip -f $copiedZipPath";
		exec( escapeshellcmd( $cmd ) );

		// now check to make sure the file you expect actually exists
		if ( !file_exists( $unzippedFullPath ) ) {
			throw new RuntimeException(
				'FILE PROBLEM: Something went wrong with decompressing WX file: ' .
				"$cmd : $unzippedFullPath doesn't exist."
			);
		}
		return $unzippedFullPath;
	}

	/**
	 * Normalize amounts, dates, and IDs to match everything else in SmashPig
	 * FIXME: do this with transformers migrated in from DonationInterface
	 *
	 * @param array $record
	 * @return array The record, with values normalized
	 */
	protected function normalizeValues( $record ) {
		if ( isset( $record['gross'] ) ) {
			$record['gross'] = $record['gross'] / 100;
		}
		if ( isset( $record['contribution_tracking_id'] ) ) {
			$parts = explode( '.', $record['contribution_tracking_id'] );
			$record['contribution_tracking_id'] = $parts[0];
		}
		if ( isset( $record['date'] ) ) {
			$record['date'] = UtcDate::getUtcTimestamp( $record['date'] );
		}
		return $record;
	}
}
