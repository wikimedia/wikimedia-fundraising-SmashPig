<?php
namespace SmashPig\Core\DataStores;

use SmashPig\Core\Context;

class QueueFactory {

	/**
	 * Retrieves an instance of a queue object described in configuration,
	 * setting the 'queue' constructor option to $queueName if missing.
	 *
	 * @param string $queueName The subkey under data-store
	 * @return mixed
	 */
	public static function getQueue( $queueName ) {
		$config = Context::get()->getConfiguration();
		$key = "data-store/$queueName";

		// Examine the config node for a queue name
		$node = $config->val( $key, true );
		if (
			empty( $node['constructor-parameters'] ) ||
			empty( $node['constructor-parameters'][0]['queue'] )
		) {
			$nameParam = array(
				'data-store' => array(
					$queueName => array(
						'constructor-parameters' => array(
							array(
								'queue' => $queueName
							)
						)
					)
				)
			);
			$config->override( $nameParam );
		}

		return $config->object( $key );
	}
}
