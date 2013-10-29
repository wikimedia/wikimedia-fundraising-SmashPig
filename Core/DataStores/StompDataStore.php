<?php namespace SmashPig\Core\DataStores;

use SmashPig\Core\Configuration;
use SmashPig\Core\Logging\Logger;

class StompDataStore extends KeyedOpaqueDataStore {

	/** @var bool If true, this class is using a PHP STOMP library, if false it is using the PEAR one */
	protected $usingPhpStomp = false;

	/** @var bool If true will use the convert_string_expressions: selector term (For ActiveMQ < 5.6 */
	protected $convertStringExpressions = false;

	/** @var \Stomp Connection object to STOMP server */
	protected $stompObj = null;

	/** @var string URI to current STOMP server */
	protected $uri = null;

	/** @var string Object on STOMP server we're subscribing and pushing to */
	protected $queue_id = null;

	/** @var int Timeout to apply to the STOMP connection */
	protected $timeout = 0;

	/**
	 * @var bool If true will force a reconnection everytime the selectors change. (Solves a bug
	 * where things in the STOMP RX buffer are old but not detected as old.)
	 */
	protected $refreshConnection = false;

	/** @var bool If true, we have successfully subscribed to @see $queue_id on @see $stompObj */
	protected $subscribed = false;

	/** @var object If not null, the current object being dealt with by the queue functions */
	protected $queueMsg = null;

	/**
	 * Create a new STOMP data store from a stored configuration node.
	 *
	 * Will connection to the server at data-store/stomp/uri
	 *
	 * @throws DataStoreException
	 * @param string $queueName The data-store/stomp/queue/* object to connect to.
	 */
	public function __construct( $queueName ) {
		$c = Configuration::getDefaultConfig();

		// Load PHP stomp if needed
		$libPath = $c->val( 'data-store/stomp/lib-path' );
		if ( !empty( $libPath ) ) {
			require_once( $libPath );
			$this->usingPhpStomp = true;
		}

		// Configuration sanity checks
		if ( !$c->nodeExists( "data-store/stomp/queues/{$queueName}" ) ) {
			throw new DataStoreException( "STOMP data store is unaware of a queue named '{$queueName}'" );
		}
		$this->queue_id = $c->val( "data-store/stomp/queues/{$queueName}" );

		// Get some more configuration variables
		$this->uri = $c->val( 'data-store/stomp/uri' );
		$this->timeout = $c->val( 'data-store/stomp/timeout' );
		$this->refreshConnection = $c->val( 'data-store/stomp/refresh-connection' );
		$this->convertStringExpressions = $c->val( 'data-store/stomp/convert-string-expressions' );

		// Start the connection
		$this->createBackingObject();
	}

	/**
	 * Destroy the STOMP data store connection.
	 */
	public function __destruct() {
		$this->deleteSubscription();
	}

	/**
	 * Creates the STOMP store backing object.
	 */
	protected function createBackingObject() {
		Logger::debug( "Attempting connection to STOMP server '{$this->uri}'" );
		$this->stompObj = new \Stomp( $this->uri );
		if ( method_exists( $this->stompObj, 'connect' ) ) {
			$this->stompObj->connect();
		}
		Logger::debug( "STOMP server connection success." );

		// Post connection configuration
		$this->stompObj->setReadTimeout( $this->timeout );
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
		$objClass = get_class( $obj );

		$objKeys = $obj->getObjectKeys();

		if ( !array_key_exists( 'correlationId', $objKeys ) ) {
			throw new DataStoreException(
				"Required property correlationId was not exposed when adding object of type $objClass."
			);
		}

		$headers = array(
			'persistent'        => 'true', // So the message doesn't disappear when the server restarts
			'php-message-class' => $objClass, // Sneakyness! No parameter can have '-' in it's name so this is safe!
		);

		// Populate with custom keys
		foreach ( $objKeys as $keyName => $keyValue ) {
			if ( $keyName == 'correlationId' ) {
				$headers[ 'JMSCorrelationID' ] = $keyValue;
			} else {
				$headers[ $keyName ] = $keyValue;
			}
		}

		// Push the object to the queue!
		$sent = $this->stompObj->send( $this->queue_id, $obj->toJson(), $headers );
		if ( !$sent ) {
			Logger::error(
				"Could not queue message ({$objClass}) with id '{$objKeys[ 'correlationId' ]}' to '{$this->queue_id}' on '{$this->uri}'",
				$obj->toJson()
			);
			throw new DataStoreException( "Could not queue message to '{$this->queue_id}' on '{$this->uri}'" );
		}
	}

	/**
	 * Remove messages with the same type and correlation ID from the
	 * persistent store.
	 *
	 * @param KeyedOpaqueStorableObject $protoObj Prototype to remove.
	 *
	 * @throws DataStoreException
	 * @return int Count of messages removed.
	 */
	public function removeObjects( KeyedOpaqueStorableObject $protoObj ) {
		$phpType = get_class( $protoObj );

		$objKeys = $protoObj->getObjectKeys();

		if ( !array_key_exists( 'correlationId', $objKeys ) ) {
			throw new DataStoreException(
				"Required property correlationId was not exposed when adding removing objects of type $objClass."
			);
		}
		$objCorrelationId = $objKeys[ 'correlationId' ];

		Logger::info(
			"Removing objects of type '$phpType' with correlation id '$objCorrelationId' from STOMP queue id {$this->queue_id}"
		);

		$count = 0;

		do {
			$msg = $this->queueGetObjectRaw( $phpType, $objCorrelationId, array(), false );
			if ( $msg ) {
				$this->queueAckObject();
				$count += 1;
			}
		} while ( $msg );

		Logger::info( "Removed $count objects from STOMP queue '{$this->queue_id}' on '{$this->uri}'" );
		return $count;
	}

	/**
	 * Remove messages with a given correlation ID from the store.
	 *
	 * @param string $id Correlation ID of messages to remove
	 *
	 * @return int Count of messages removed.
	 */
	public function removeObjectsById( $id ) {
		$count = 0;

		Logger::info(
			"Removing objects with with correlation id '$id' from STOMP queue id {$this->queue_id}"
		);

		do {
			$msg = $this->queueGetObjectRaw( null, $id, array(), false );
			if ( $msg ) {
				$this->queueAckObject();
				$count += 1;
			}
		} while ( $msg );

		Logger::info( "Removed $count objects from STOMP queue '{$this->queue_id}' on '{$this->uri}'" );
		return $count;
	}

	/**
	 * Operate the datastore as a queue. Will retrieve messages, one at a time,
	 * from the backing store ensuring that no other running process may obtain
	 * the same message.
	 *
	 * Any message obtained via this function must be either acknowledged (and
	 * thus removed from the backing store) or ignored (whereby it is replaced
	 * into the backing store). Only once one of these operations is completed
	 * may another message be obtained from the backing store.
	 *
	 * If a message has not yet been completely when this function gets called,
	 * it will throw a DataStoreTransactionException exception.
	 *
	 * If there were no messages fitting the filter, null will be returned.
	 *
	 * @param null|string    $type      The class of message to retrieve (if null retrieves all)
	 * @param null|string    $id        The correlation ID of the message (if null retrieves all)
	 * @param string[]       $customSelectors Array of custom STOMP selectors (e.g 'gateway=adyen')
	 * @param bool           $deserialize If true will return an object. Otherwise will return an array
	 *                                    with keys 'headers' and 'body'.
	 *
	 * @throws DataStoreTransactionException
	 * @throws DataSerializationException
	 * @throws DataStoreException
	 * @return KeyedOpaqueStorableObject|array|null
	 */
	public function queueGetObject( $type = null, $id = null, $customSelectors = array(), $deserialize = true ) {
		$msgObj = $this->queueGetObjectRaw( $type, $id, $customSelectors );

		if ( $msgObj && $deserialize ) {
			if ( !array_key_exists( 'php-message-class', $msgObj->headers ) ) {
				Logger::warning(
					"Message was serialized without key php-message-class. Cannot re-instantiate.",
					$msgObj
				);
				$this->queueIgnoreObject();

				// I'm not a huge fan of this recursion; but I don't know what else to do
				return $this->queueGetObject( $type, $id, $customSelectors );
			}

			$className = $msgObj->headers[ 'php-message-class' ];
			if ( !class_exists( $className ) ) {
				Logger::warning(
					"DataStore cannot instantiate object from message. No such class '{$className}'.",
					$msgObj
				);
				throw new DataStoreException( "Cannot instantiate class '{$className}'; no such class exists." );
			}

			try {
				$classObj = KeyedOpaqueStorableObject::fromJsonProxy( $className, $msgObj->body );
				$classObj->correlationId = $msgObj->headers[ 'correlation-id' ];
			} catch ( DataSerializationException $ex ) {
				Logger::warning(
					"DataStore cannot instantiate object from STOMP message.",
					$msgObj,
					$ex
				);
				throw $ex;
			}

			return $classObj;
		} else if ( $msgObj && !$deserialize ) {
			return array(
				'headers' => $msgObj->headers,
				'body' => $msgObj->body,
			);
		} else {
			return null;
		}
	}

	/**
	 * Backing function for queueGetObject; retrieves, using STOMP selectors,
	 * a requested message from the queue and starts a transaction for it.
	 *
	 * @param null     $type            Object type to select on
	 * @param null     $id              Correlation ID to select on
	 * @param string[] $customSelectors Array of custom STOMP selectors (e.g 'gateway=adyen')
	 * @param bool     $checkTail       If true (default) will stop getting new objects when
	 *                                  new objects are being returned with the current STOMP
	 *                                  transaction ID in them.
	 *
	 * @throws DataStoreTransactionException
	 * @return object STOMP message object
	 */
	protected function queueGetObjectRaw( $type = null, $id = null, $customSelectors = array(), $checkTail = true ) {
		if ( $this->queueMsg ) {
			throw new DataStoreTransactionException(
				"STOMP transaction already in progress. Cannot request new object at this time."
			);
		}

		$this->createSubscription( $type, $id, $customSelectors );

		Logger::debug( "Pulling new object from queue" );
		try {
			$this->queueMsg = $this->stompObj->readFrame();
		} catch ( \Exception $ex ) {
			Logger::error( "STOMP threw an unexpected exception on readFrame()", null, $ex );
			$this->queueMsg = null;
		}

		if ( $this->queueMsg ) {
			if ( $checkTail && strpos(
					$this->queueMsg->headers[ 'message-id' ],
					$this->stompObj->getSessionId()
				) === 0
			) {
				// We've started to see things from our own session. This means we've started to eat our
				// own tail and it's time to exit.
				Logger::debug( "Detected tail eating! Stopping queue consumption." );
				return null;
			} else {
				Logger::debug( "Pulled new object from STOMP queue {$this->queue_id}" );
				return $this->queueMsg;
			}
		} else {
			Logger::info( "STOMP did not return an object" );
			return null;
		}
	}

	/**
	 * Creates or re-uses a subscription to the STOMP backing store. There can only be one active
	 * subscription at a time so if the requested subscription does not match the previous one,
	 * the old one is unsubscribed and a new one is started.
	 *
	 * @param null|string $type   Object type to select on
	 * @param null|string $id     Correlation ID to select on
	 * @param string[]    $custom Array of STOMP selector strings, like "gateway=adyen"
	 */
	protected function createSubscription( $type, $id, $custom = array() ) {
		static $sType, $sId, $sCustom;
		$properties = array(
			'ack' => 'client-individual',
		);

		sort( $custom );

		if ( $this->subscribed && ( $sType === $type ) && ( $sId === $id ) && !array_diff( $sCustom, $custom ) ) {
			// Same subscription; just return
			return;
		} elseif ( $this->subscribed ) {
			// We need to create a new subscription; but we also have to delete the old one
			$this->deleteSubscription();

			if ( $this->refreshConnection ) {
				// Apparently the backend STOMP library has some issues clearing
				// out its buffer so we get old stuff :(
				Logger::debug( "Refreshing STOMP connection object on selector change. refresh-connection = true" );
				$this->createBackingObject();
			}
		}

		$sType = $type;
		$sId = $id;
		$sCustom = $custom;

		// Sanitize the custom selectors
		foreach ( $custom as &$selector ) {
			$groups = array();
			$result = preg_match( '/([^\\=]+)([<>]\\=?|!?\\=)([^\\=]+)/', $selector, $groups );
			if ( $result != 1 ) {
				Logger::warning( "Custom STOMP selector doesn't make sense: $selector" );
				continue;
			}

			$key = $groups[1];
			$operator = $groups[2];
			$value = $groups[3];

			if ( is_numeric( $value ) === true ) {
				if ( $this->convertStringExpressions ) {
					// See http://activemq.apache.org/selectors.html
					$key = "convert_string_expressions:{$key}";
				} else {
					// Nothing, the key is already in canonical form
				}
			} else {
				$value = trim( $value, " '\"" );
				$value = "'{$value}'";
			}
			$selector = "{$key}{$operator}{$value}";
		}

		// And build the selection header array
		$selectorQuery = array();
		if ( $type ) {
			$selectorQuery[] = "php-message-class='$type'";
		}
		if ( $id ) {
			$selectorQuery[] = "JMSCorrelationID='$id'";
		}
		$selectorQuery += $custom;
		if ( $selectorQuery ) {
			$properties[ 'selector' ] = implode( ' AND ', $selectorQuery );
		}

		Logger::debug( "Attempting to STOMP subscribe to '{$this->queue_id}' on '{$this->uri}'", $properties );
		$this->stompObj->subscribe( $this->queue_id, $properties );

		$this->subscribed = true;
	}

	/**
	 * Removes an active subscription, if it exists, to the STOMP backing store.
	 */
	protected function deleteSubscription() {
		if ( $this->subscribed ) {
			Logger::debug( "Unsubscribing from STOMP queue '{$this->queue_id}'" );
			try {
				// Sometimes the resource has already been destroyed by some other
				// means and STOMP throws an exception.
				$this->stompObj->unsubscribe( $this->queue_id );
			} catch ( \Stomp_Exception $ex ) {
				// Yay for generic errors! We never do that... no... >.>
			}
			$this->subscribed = false;
		}
	}

	/**
	 * Acknowledges and removes, from the backing data store, the current queue message
	 */
	public function queueAckObject() {
		if ( !$this->queueMsg ) {
			throw new DataStoreTransactionException(
				"No STOMP transaction currently in progress. Cannot ACK a non-existent message!"
			);
		}

		Logger::debug( "Acking STOMP message" );
		$this->stompObj->ack( $this->queueMsg );

		$this->queueMsg = null;
		Logger::info( "STOMP message acked from '{$this->queue_id}' on '{$this->uri}'" );
	}

	/**
	 * Acknowledges and replaces, into the backing data store, the current queue message
	 */
	public function queueIgnoreObject() {
		if ( !$this->queueMsg ) {
			throw new DataStoreTransactionException(
				"No STOMP transaction currently in progress. Cannot requeue a non-existent message!"
			);
		}

		Logger::debug( "Acking STOMP message" );
		$this->stompObj->ack( $this->queueMsg );

		Logger::debug( "Re-adding STOMP message to queue" );
		$sent = $this->stompObj->send( $this->queue_id, $this->queueMsg->body, $this->queueMsg->headers );

		if ( !$sent ) {
			Logger::error( "Could not re-queue message to '{$this->queue_id}' on '{$this->uri}'", $this->queueMsg );
			throw new DataStoreException( "Could not re-queue message to '{$this->queue_id}' on '{$this->uri}'" );
		}

		$this->queueMsg = null;
		Logger::info( "STOMP message requeued from '{$this->queue_id}' on '{$this->uri}'" );
	}
}
