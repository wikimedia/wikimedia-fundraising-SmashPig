<?php namespace SmashPig\Core\DataStores;

/**
 * Abstraction of a concept of a place to store persistent data that can be
 * identified by a type and may be related to other items by an ID.
 */
abstract class KeyedOpaqueDataStore {

	/**
	 * Adds an object to the persistent data store.
	 *
	 * @param KeyedOpaqueStorableObject $obj
	 *
	 * @throws DataStoreException if the message could not be stored.
	 * @return null
	 */
	abstract public function addObject( KeyedOpaqueStorableObject $obj );

	/**
	 * Remove object with the same serialization type and correlation ID from the
	 * persistent store.
	 *
	 * @param KeyedOpaqueStorableObject $protoObj Prototype to remove.
	 *
	 * @return int Count of messages removed.
	 */
	abstract public function removeObjects( KeyedOpaqueStorableObject $protoObj );

	/**
	 * Remove objects with a given correlation ID from the store.
	 *
	 * @param string $id Correlation ID of messages to remove
	 *
	 * @return int Count of messages removed.
	 */
	abstract public function removeObjectsById( $id );

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
	abstract public function queueGetObject( $type = null, $id = null );

	/**
	 * Acknowledges and removes from the backing data store the current queue object
	 */
	abstract public function queueAckObject();

	/**
	 * Acknowledges and replaces into the backing data store the current queue object
	 */
	abstract public function queueIgnoreObject();
}