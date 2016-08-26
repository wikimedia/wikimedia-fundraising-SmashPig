<?php namespace SmashPig\Tests;

use SmashPig\Core\Configuration;

class SmashPigDatabaseTestConfiguration extends Configuration {
	public static function instance() {
		return Configuration::createForViewWithOverrideFile(
			'default',
			__DIR__ . '/data/config_smashpig_db.yaml'
		);
	}
}
