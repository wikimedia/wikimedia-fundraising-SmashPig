<?php namespace SmashPig\Core\DataStores;

/**
 * Base class for any object that may be stored into an opaque backing store.
 *
 * Provides generic serialization/deserialization routines.
 *
 * Classes inheriting from here should pay close attention to the __construct() and
 * serializedConstructor() functions as well as the $propertiesExcludedFromExport
 * array.
 */
abstract class KeyedOpaqueStorableObject extends JsonSerializableObject {

	/** @var string Primary grouping identifier - objects that are strongly related should use the same correlationId */
	public $correlationId = '';

	/** @var array List of object properties that can be considered 'identifying' or 'filtering' properties */
	protected $propertiesExportedAsKeys = array( 'correlationId' );

	/**
	 * Obtains all the properties of the message that are declared as 'keys' via the
	 * $propertiesExportedAsKeys list. Any 'key' should be some type of identifying
	 * property to the message -- this includes gateway name, account name, transaction
	 * id, etc. Keys must be coercible to strings.
	 *
	 * The only required key is 'correlationId' which should group messages that are
	 * related.
	 *
	 * @return array Keys are property names, values are string cast.
	 */
	public function getObjectKeys() {
		$properties = array();

		foreach ( $this->propertiesExportedAsKeys as $propName ) {
			$properties[ $propName ] = (string)$this->$propName;
		}

		return $properties;
	}
}
