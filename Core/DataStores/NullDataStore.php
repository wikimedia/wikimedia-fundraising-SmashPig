<?php

namespace SmashPig\Core\DataStores;

/**
 * Class NullDataStore
 *
 * Stub datastore to use when a datastore is not desirable to
 * be used. E.g. this can be subbed into the inflight data
 * store if that redundancy is not desired.
 *
 * @package SmashPig\Core\DataStores
 */
class NullDataStore extends KeyedOpaqueDataStore {
	/**
	 * Drops a message to the floor
	 *
	 * @param KeyedOpaqueStorableObject $obj
	 * @return null
	 */
	public function addObject( KeyedOpaqueStorableObject $obj ) {
	}

	/**
	 * Would remove a message, but we aren't storing them.
	 *
	 * @param KeyedOpaqueStorableObject $protoObj Prototype to remove.
	 *
	 * @return int Always zero
	 */
	public function removeObjects( KeyedOpaqueStorableObject $protoObj ) {
		return 0;
	}

	/**
	 * Would remove a message, but we aren't storing them.
	 *
	 * @param string $id Correlation ID of messages to remove
	 *
	 * @return int Always zero
	 */
	public function removeObjectsById( $id ) {
		return 0;
	}

	/**
	 * Null object generator :)
	 *
	 * @param string|null $type The class of object to retrieve (if null retrieves all)
	 * @param null|string $id   The correlation ID of the message (if null retrieves all)
	 *
	 * @throws DataStoreTransactionException
	 * @return KeyedOpaqueStorableObject|null
	 */
	public function queueGetObject( $type = null, $id = null ) {
		return null;
	}

	/**
	 * Null operation, cannot ack and remove a null object
	 */
	public function queueAckObject() {
	}

	/**
	 * Null operation, cannot ack and replace a null object
	 */
	public function queueIgnoreObject() {
	}
}
