<?php namespace SmashPig\Tests;

use SmashPig\Core\Configuration;

class QueueTestConfiguration extends Configuration {
	public function __construct() {
		parent::__construct(
			'default',
			__DIR__ . '/data/config_queue.yaml'
		);
	}
}
