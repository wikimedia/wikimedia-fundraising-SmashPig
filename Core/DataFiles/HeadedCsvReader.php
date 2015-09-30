<?php namespace SmashPig\Core\DataFiles;

/**
 * Iteratively reads a CSV file that contains a data header
 *
 * @package SmashPig\Core\DataFiles
 */
class HeadedCsvReader extends CsvReader {
	/** @var string[] */
	protected $colNames;
	protected $colIndexes;

	public function __construct( $file, $delimiter = ',', $maxRowLength = 4098 ) {
		parent::__construct( $file, $delimiter, $maxRowLength );

		// Extract the header information
		$this->colNames = parent::current();
		foreach( $this->colNames as $index => $name ) {
			$this->colIndexes[$name] = $index;
		}
		parent::next();
	}

	/**
	 * Extract the contents of the given column name.
	 *
	 * This is slightly backwards because it did not seem worth the effort
	 * to create a fully functional ArrayObject class to return from current()
	 *
	 * @param $colName string Name of the column to extract
	 * @param $row string[] A row returned from current() **FROM THIS FILE**
	 *
	 * @throws DataFileException if the column name does not exist.
	 * @return string Contents of the column
	 */
	public function extractCol( $colName, &$row ) {
		if ( !isset( $this->colIndexes[$colName] ) ) {
			throw new DataFileException( "Column name {$colName} not found!" );
		}
		$index = $this->colIndexes[$colName];
		return $row[$index];
	}

	/**
	 * @return string[] CSV file headers in order of columns
	 */
	public function headers() {
		return $this->colNames;
	}
}