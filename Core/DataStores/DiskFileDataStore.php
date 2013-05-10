<?php namespace SmashPig\Core\DataStores;

use SmashPig\Core\AutoLoader;
use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;

class DiskFileDataStore extends KeyedOpaqueDataStore {
	protected $basePath = '';
	protected $objectsPath = '';
	protected $keysPath = '';

	protected $count = 0;

	public function __construct( $path ) {
		$this->basePath = AutoLoader::makePath( $path );
		$this->objectsPath = AutoLoader::makePath( $this->basePath, 'objects' );
		$this->keysPath = AutoLoader::makePath( $this->basePath, 'keys' );

		Logger::debug( "Constructing DiskFileStore with path {$this->basePath}" );

		if ( !file_exists( $this->basePath ) && !mkdir( $this->basePath, 0770, true ) ) {
			Logger::info( "Could not create base store directory $this->basePath" );
			throw new DataStoreException( "Could not create writeable directory: '$this->basePath'" );
		}

		if ( !file_exists( $this->objectsPath ) && !mkdir( $this->objectsPath, 0770, true ) ) {
			Logger::info( "Could not create object store directory $this->objectsPath" );
			throw new DataStoreException( "Could not create writeable directory: '$this->objectsPath'" );
		}

		if ( !file_exists( $this->keysPath ) && !mkdir( $this->keysPath, 0770, true ) ) {
			Logger::info( "Could not create key links store directory $this->keysPath" );
			throw new DataStoreException( "Could not create writeable directory: '$this->keysPath'" );
		}
	}

	/**
	 * Adds an object to the persistent data store.
	 *
	 * @param KeyedOpaqueStorableObject $obj
	 *
	 * @throws DataStoreException if the message could not be stored.
	 * @return null
	 */
	public function addObject( KeyedOpaqueStorableObject $obj ) {
		$keys = $obj->getObjectKeys();

		$objFileName = $this->constructFileName(
			Context::get()->getContextId(),
			$keys,
			$this->count++
		);
		$objFsPath = AutoLoader::makePath(
			$this->basePath,
			'objects',
			$objFileName
		);

		/* --- Create the root object file --- */
		if ( ( !file_exists( $objFsPath ) ) || ( ( $fptr = fopen( $objFsPath, 'xb' ) ) === false ) ) {
			throw new DataStoreException(
				"Could not add object to store! Fully qualified key '$objFileName' already exists!"
			);
		}
		fwrite( $fptr, get_class( $obj ) );
		fwrite( $fptr, "\n" );
		fwrite( $fptr, $obj->toJson() );
		fclose( $fptr );

		/* === Create the helper linking files === */
		/* --- Class file first --- */
		$this->addKeyedLinkingFile( 'class', get_class( $obj ), $objFileName, $objFsPath );

		/* --- Everything else --- */
		foreach( $keys as $key => $value ) {
			$this->addKeyedLinkingFile( $key, $value, $objFileName, $objFsPath );
		}
	}

	/**
	 * Remove objects with the same serialization type and correlation ID from the
	 * persistent store.
	 *
	 * @param KeyedOpaqueStorableObject $protoObj Prototype to remove.
	 *
	 * @return int Count of messages removed.
	 */
	public function removeObjects( KeyedOpaqueStorableObject $protoObj ) {
		$className = get_class( $protoObj );

		$count = 0;

		// Look up all by correlation ID and then remove them if they match in keys/classes
		$idpath = AutoLoader::makePath(
			$this->keysPath,
			'correlationId',
			DiskFileDataStore::escapeName( $protoObj->correlationId ),
			'*'
		);
		$classpath = AutoLoader::makePath(
			$this->keysPath,
			'class',
			$this->escapeName( $className )
		);
		foreach ( glob( $idpath ) as $filename ) {
			$filePathParts = explode( DIRECTORY_SEPARATOR, $filename );
			$filename = end( $filePathParts );
			if ( file_exists( AutoLoader::makePath( $classpath, $filename ) ) ) {
				// It's a match! DELETE IT ALL!
				unlink( AutoLoader::makePath( $this->objectsPath, $filename ) );
				$this->removeKeyedLinkingFile( 'class', $className, $filename );
				foreach( explode( '.', $filename ) as $key ) {
					$parts = explode( '=', $key );
					if ( count( $parts ) === 2 ) {
						$this->removeKeyedLinkingFile( $parts[0], $parts[1], $filename );
					} else {
						Logger::error(
							"Whilst removing a disk linked file '$filename', I encountered a strange key and might have missed a file."
						);
					}
				}
				$count++;
			}
		};

		return $count;
	}

	/**
	 * Remove objects with a given correlation ID from the store.
	 *
	 * @param string $id Correlation ID of messages to remove
	 *
	 * @return int Count of messages removed.
	 */
	public function removeObjectsById( $id ) {
		$count = 0;

		// Look up all by correlation ID and then remove them if they match in keys/classes
		$idpath = AutoLoader::makePath(
			$this->keysPath,
			'correlationId',
			DiskFileDataStore::escapeName( $protoObj->correlationId ),
			'*'
		);
		foreach ( glob( $idpath ) as $filename ) {
			$filePathParts = explode( DIRECTORY_SEPARATOR, $filename );
			$filename = end( $filePathParts );

			// Load the first line of the object to get the classname
			$objPath = AutoLoader::makePath( $this->objectsPath, $filename );
			$fptr = fopen( $objPath, 'rb' );
			$className = fgets( $fptr );
			fclose( $fptr );

			// Now delete everything
			unlink( $objPath );
			$this->removeKeyedLinkingFile( 'class', $className, $filename );
			foreach( explode( '.', $filename ) as $key ) {
				$parts = explode( '=', $key );
				if ( count( $parts ) === 2 ) {
					$this->removeKeyedLinkingFile( $parts[0], $parts[1], $filename );
				}
			}
			$count++;
		};

		return $count;
	}

	/**
	 * Operate the datastore as a queue. Will retrieve objects, one at a time,
	 * from the backing store ensuring that no other running process may obtain
	 * the same message.
	 *
	 * Any message obtained via this function must be either acknowledged (and
	 * thus removed from the backing store) or ignored (whereby it is replaced
	 * into the backing store). Only once one of these operations is completed
	 * may another object be obtained from the backing store.
	 *
	 * If a object has not yet been completely when this function gets called,
	 * it will throw a DataStoreTransactionException exception.
	 *
	 * If there were no object fitting the filter, null will be returned.
	 *
	 * @param string|null    $type      The class of object to retrieve (if null retrieves all)
	 * @param null|string    $id        The correlation ID of the message (if null retrieves all)
	 *
	 * @throws DataStoreTransactionException
	 * @return KeyedOpaqueStorableObject|null
	 */
	public function queueGetObject( $type = null, $id = null ) {
		throw new SmashPigException( "Not implemented!" );
	}

	/**
	 * Acknowledges and removes from the backing data store the current queue object
	 */
	public function queueAckObject() {
		throw new SmashPigException( "Not implemented!" );
	}

	/**
	 * Acknowledges and replaces into the backing data store the current queue object
	 */
	public function queueIgnoreObject() {
		throw new SmashPigException( "Not implemented!" );
	}

	/**
	 * Constructs a file name from various parts
	 *
	 * @param string|array $params Multiple parameters which may be either a string or
	 *                             an array. Arrays are assumed to be 2D and will be
	 *                             represented as key=value. Parts are joined with '.'
	 *
	 * @return string
	 */
	protected function constructFileName( $params ) {
		$params = func_get_args();

		$fname = array();
		foreach ( $params as $item ) {
			if ( is_array( $item ) ) {
				foreach ( $item as $key => $value ) {
					$key = DiskFileDataStore::escapeName( $key );
					$value = DiskFileDataStore::escapeName( $value );
					$fname[] = "$key=$value";
				}
			} else {
				$fname[] = DiskFileDataStore::escapeName( $item );
			}
		}

		return implode( '.', $fname );
	}

	/**
	 * Escapes a string such that it is suitable for the file system
	 *
	 * @param string $s String to be escaped
	 *
	 * @return mixed Escaped string
	 */
	protected static function escapeName( $s ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '_', $s );
	}

	/**
	 * Adds a symbolic link to the filesystem to $linkPath
	 *
	 * @param string $key       Name of key, e.g. 'correlationId'. Makes a new subdirectory under ./keys/
	 * @param string $value     Value of the key. Makes a new subdirectory under ./keys/$key/
	 * @param string $linkName  Name of the linking file
	 * @param string $linkPath  Path to the linking file not including the $linkName
	 *
	 * @throws DataStoreException
	 */
	protected function addKeyedLinkingFile( $key, $value, $linkName, $linkPath ) {
		$path = AutoLoader::makePath(
			$this->keysPath,
			DiskFileDataStore::escapeName( $key ),
			DiskFileDataStore::escapeName( $value )
		);

		if ( !file_exists( $path ) && !mkdir( $path, 0770, true ) ) {
			throw new DataStoreException( "Could not create path '$path' for linking store." );
		}
		if ( !symlink( $linkPath, AutoLoader::makePath( $path, $linkName ) ) ) {
			throw new DataStoreException( "Could not create link '$linkName' for linking store." );
		}
	}

	/**
	 * Removes an file system entry created by addKeyedLinkingFile. Will remove empty directories
	 *
	 * @param string $key       Name of key, e.g. 'correlationId'.
	 * @param string $value     Value of the key.
	 * @param string $linkName  Name of the linking file to remove
	 */
	protected function removeKeyedLinkingFile( $key, $value, $linkName ) {
		$path = AutoLoader::makePath(
			$this->basePath,
			'keys',
			DiskFileDataStore::escapeName( $key ),
			DiskFileDataStore::escapeName( $value )
		);
		unlink( AutoLoader::makePath( $path, $linkName ) );

		// Do some cleanup if possible
		if ( $this->isDirEmpty( $path ) ) {
			rmdir( $path );
		}
	}

	/**
	 * Determine if a file system directory is empty.
	 *
	 * @param string $dir Directory path
	 *
	 * @return bool True if empty
	 */
	protected function isDirEmpty( $dir ) {
		$retval = true;

		$handle = opendir( $dir );
		while ( ( $entry = readdir( $handle ) ) !== false ) {
			if ( $entry !== '.' && $entry !== '..' ) {
				$retval = false;
				break;
			}
		}
		closedir( $handle );
		return $retval;
	}
}
