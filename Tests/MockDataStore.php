<?php
use SmashPig\Core\DataStores\DataStoreException;
use SmashPig\Core\DataStores\DataStoreTransactionException;
use SmashPig\Core\DataStores\KeyedOpaqueDataStore;
use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\Core\SmashPigException;

/**
 * Fakes some data store behavior for tests
 * TODO: anything that cares about message type
 * Class MockDataStore
 */
class MockDataStore extends KeyedOpaqueDataStore {

	protected $messages = array();

	protected $currentQueue = null;

	public function __construct() {}

	/**
	 * Adds an object to the persistent data store.
	 *
	 * @param \SmashPig\Core\DataStores\KeyedOpaqueStorableObject $obj
	 *
	 * @throws \SmashPig\Core\DataStores\DataStoreException if the message could not be stored.
	 * @return null
	 */
	public function addObject( KeyedOpaqueStorableObject $obj ) {
		$keys = $obj->getObjectKeys();
		if ( !array_key_exists( 'correlationId', $keys ) ) {
			throw new DataStoreException(
				"Required property correlationId was not exposed."
			);
		}
		$corrId = $keys['correlationId'];
		if ( !isset( $this->messages[$corrId] ) ) {
			$this->messages[$corrId] = array();
		}
		array_push( $this->messages[$corrId], $obj );
	}

	/**
	 * Remove objects with the same serialization type and correlation ID from the
	 * persistent store.
	 * @param \SmashPig\Core\DataStores\KeyedOpaqueStorableObject $protoObj Prototype to remove.
	 *
	 * @return int Count of messages removed.
	 */
	public function removeObjects( KeyedOpaqueStorableObject $protoObj ) {
		// TODO
	}

	/**
	 * Remove objects with a given correlation ID from the store.
	 *
	 * @param string $id Correlation ID of messages to remove
	 *
	 * @return int Count of messages removed.
	 */
	public function removeObjectsById( $id ) {
		if ( isset( $this->messages[$id] ) ) {
			unset( $this->messages[$id] );
		}
	}

	/**
	 *
	 * If a object has not yet been completely acked when this function gets called,
	 * it will throw a DataStoreTransactionException exception.
	 *
	 * If there were no objects fitting the filter, null will be returned.
	 *
	 * @param string|null $type ignored
	 * @param null|string $id The correlation ID of the message
	 *
	 * @throws \SmashPig\Core\DataStores\DataStoreTransactionException
	 * @return KeyedOpaqueStorableObject|null
	 */
	public function queueGetObject( $type = null, $id = null ) {
		if ( $id === null ) {
			throw new SmashPigException( 'Need id for mock queue' );
		}
		if ( empty( $this->messages[$id] ) ) {
			return null;
		}
		$this->currentQueue = & $this->messages[$id];
		return $this->currentQueue[0];
	}

	/**
	 * Acknowledges and removes from the backing data store the current queue object
	 */
	public function queueAckObject() {
		if ( !$this->currentQueue ) {
			throw new DataStoreTransactionException(
				"No STOMP transaction currently in progress. Cannot ACK a non-existent message!"
			);
		}
		array_shift( $this->currentQueue );
	}

	/**
	 * Acknowledges and replaces into the backing data store the current queue object
	 */
	public function queueIgnoreObject() {
		if ( !$this->currentQueue ) {
			throw new DataStoreTransactionException(
				"No STOMP transaction currently in progress. Cannot ACK a non-existent message!"
			);
		}
		$currentMessage = $this->currentQueue[0];
		$this->queueAckObject();
		$this->addObject( $currentMessage );
	}
}
