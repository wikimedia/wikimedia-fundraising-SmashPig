<?php namespace SmashPig\Core\DataStores;

use PDO;
use PHPQueue\Exception\Exception;
use SmashPig\Core\Context;

abstract class SmashPigDatabase {

	/**
	 * @var PDO
	 * We do the silly singleton thing for convenient testing with in-memory
	 * databases that would otherwise not be shared between components.
	 */
	protected static $db;

	protected function __construct() {
		$config = Context::get()->getConfiguration();
		if ( !static::$db ) {
			static::$db = $config->object( $this->getConfigKey() );
		}
	}

	public static function get() {
		return new static();
	}

	/**
	 * @return PDO
	 */
	public function getDatabase() {
		return static::$db;
	}

	public function createTable() {
		$driver = $this->getDatabase()->getAttribute( PDO::ATTR_DRIVER_NAME );
		$path = __DIR__ . '/../../Schema/'
			. $driver . '/' . $this->getTableScriptFile();
		$this->getDatabase()->exec( file_get_contents( $path ) );
	}

	/**
	 * @return string Key in configuration pointing to backing PDO object
	 */
	abstract protected function getConfigKey();

	/**
	 * @return string Name of file (no directory) containing table creation SQL
	 */
	abstract protected function getTableScriptFile();

	/**
	 * Build components of a parameterized insert statement
	 *
	 * @param array $record the associative array of values
	 * @return array with two string members, first a concatenated field list,
	 *  then a concatenated list of parameters.
	 */
	protected static function formatInsertParameters( $record ) {
		$fields = array_keys( $record );
		$fieldList = implode( ',', $fields );

		// Build a list of parameter names for safe db insert
		// Same as the field list, but each parameter is prefixed with a colon
		$paramList = ':' . implode( ', :', $fields );
		return array( $fieldList, $paramList );
	}

	/**
	 * Prepares and executes a database command
	 *
	 * @param string $sql parameterized SQL command
	 * @param array $dbRecord associative array of values to bind
	 * @return bool true if execution succeeded
	 */
	protected function prepareAndExecute( $sql, $dbRecord ) {
		$prepared = $this->getDatabase()->prepare( $sql );

		foreach ( $dbRecord as $field => $value ) {
			if( gettype( $value ) === 'integer' ) {
				$paramType = PDO::PARAM_INT;
			} else {
				$paramType = PDO::PARAM_STR;
			}
			$prepared->bindValue(
				':' . $field,
				$value,
				$paramType
			);
		}
		return $prepared->execute();
	}
}
