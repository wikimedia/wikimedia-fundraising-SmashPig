<?php

namespace SmashPig\Tests;

use \SmashPig\Core\Configuration;

class PendingDatabaseTestConfiguration extends Configuration {
	public function __construct() {
		parent::__construct(
			'default',
			__DIR__ . '/data/config_pending_db.yaml'
		);
	}
}
