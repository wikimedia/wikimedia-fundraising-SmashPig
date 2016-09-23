<?php namespace SmashPig\Core\DataStores;

use PDO;
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
}
