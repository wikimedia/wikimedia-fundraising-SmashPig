<?php namespace SmashPig\Maintenance;

require ( 'MaintenanceBase.php' );

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;
use SmashPig\Core\DataStores\KeyedOpaqueDataStore;

$maintClass = '\SmashPig\Maintenance\TestDatastore';

/**
 * Test a datastore connection to ensure that it can store and retrieve objects
 * correctly.
 */
class TestDatastore extends MaintenanceBase {

	/** @var KeyedOpaqueDataStore  */
	protected $datastore = null;

	/** @var TestObject $testObjects */
	protected $testObjects = array();

	public function __construct() {
		parent::__construct();
		$this->addArgument( 'queue', 'Queue datastore to test', true );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$this->datastore = Context::get()->getConfiguration()->object(
			'data-store/' . $this->getArgument( 0, 'test' ),
			false
		);

		// Generate a whole bunch of random data
		while ( count( $this->testObjects ) < 10 ) {
			$this->testObjects[] = TestObject::factory();
		}
		// And repeat the objects and inject so we have something else to find
		foreach ( $this->testObjects as $obj ) {
			$this->datastore->addObject( $obj );
			$this->datastore->addObject( TestObject::factory( $obj->correlationId ) );
		}

		// Mix up the order of the objects to simulate real life
		shuffle( $this->testObjects );

		// Now attempt to find them and their pairs!
		$this->datastore = Context::get()->getConfiguration()->object(
			'data-store/' . $this->getArgument( 0, 'test' ),
			false
		);
		foreach ( $this->testObjects as $obj ) {
			$obj1 = $this->datastore->queueGetObject( null, $obj->correlationId );
			if ( $obj1 !== null ) { $this->datastore->queueAckObject();
	  }
			else { $this->error( "Could not find original object with id {$obj->correlationId}" );
continue;
	  }

			$obj2 = $this->datastore->queueGetObject( null, $obj->correlationId );
			if ( $obj2 !== null ) { $this->datastore->queueAckObject();
	  }
			else { $this->error( "Could not find secondary object with id {$obj->correlationId}" );
continue;
	  }

			$obj3 = $this->datastore->queueGetObject( null, $obj->correlationId );
			if ( $obj3 !== null ) {
				$this->datastore->queueAckObject();
				$this->error( "Found tertiary object with id {$obj3->correlationId} "
					. "while looking for id {$obj->correlationId}" );
				continue;
			}

			Logger::info( "Successfully found id {$obj->correlationId}" );
		}

		Logger::info( "Done" );
	}

	protected function throwException() {
		throw new SmashPigException( 'TestException!' );
	}
}

class TestObject extends KeyedOpaqueStorableObject {
	/** @var array List of object properties that can be considered
	 * 'identifying' or 'filtering' properties
	 */
	protected $propertiesExportedAsKeys = array( 'correlationId', 'testkey1', 'testkey2' );

	public $testkey1 = null;
	public $testkey2 = null;

	public static function factory( $id = null ) {
		$obj = new TestObject();
		$obj->correlationId = ( $id !== null ) ? $id : mt_rand();
		$obj->testkey1 = mt_rand();
		$obj->testkey2 = mt_rand();

		return $obj;
	}
}

require ( RUN_MAINTENANCE_IF_MAIN );
