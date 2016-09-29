<?php namespace SmashPig\Core\DataStores;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Configuration;
use SmashPig\Core\ConfigurationException;

/**
 * Write to multiple queue backends
 */
class MultiQueueWriter {

	protected $queues = array();

	/**
	 * MultiQueueWriter constructor.
	 *
	 * @param array $backends list of config keys under data-store
	 */
	public function __construct( $backends ) {
		$config = Configuration::getDefaultConfig();
		foreach ( $backends as $configKey ) {
			$path = 'data-store/' . $configKey;
			$this->queues[] = $config->object( $path );
		}
	}

	public function push( KeyedOpaqueStorableObject $message ) {
		foreach( $this->queues as $queue ) {
			if ( $queue instanceof KeyedOpaqueDataStore ) {
				$queue->addObject( $message );
			} else if ( $queue instanceof FifoQueueStore ) {
				$messageArray = json_decode( $message->toJson(), true );
				$queue->push( $messageArray );
			} else {
				throw new ConfigurationException(
					'MultiQueueWriter can\'t handle ' . get_class( $queue )
				);
			}
		}
	}
}
