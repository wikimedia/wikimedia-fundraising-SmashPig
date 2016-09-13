<?php namespace SmashPig\Tests;

use SmashPig\Core\Configuration;

class QueueTestConfiguration extends Configuration {

	public static function instance() {
		return self::createForViewWithOverrideFile(
			'default',
			__DIR__ . '/data/config_queue.yaml'
		);
	}
}
