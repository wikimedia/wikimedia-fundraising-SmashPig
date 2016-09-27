<?php namespace SmashPig\Core\DataStores;

use PHPQueue\Interfaces\FifoQueueStore;
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
		foreach ( $backends as $configKey ) {
			$this->queues[] = QueueFactory::getQueue( $configKey );
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
