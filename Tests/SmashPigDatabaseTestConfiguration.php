<?php namespace SmashPig\Tests;

use SmashPig\Core\Configuration;

class SmashPigDatabaseTestConfiguration extends Configuration {
	public function __construct() {
		parent::__construct(
			'default',
			__DIR__ . '/data/config_smashpig_db.yaml'
		);
	}
}
