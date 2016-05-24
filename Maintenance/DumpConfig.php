<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

use Symfony\Component\Yaml\Yaml;

$maintClass = 'SmashPig\Maintenance\DumpConfig';

/**
 * Diagnostic tool to print materialized configuration values to stdout.
 */
class DumpConfig extends MaintenanceBase {
	public function execute() {
		$context = Context::get();
		$config = $context->getConfiguration();
		$values = $config->val('/');
		$yaml = Yaml::dump($values);

		print $yaml;
	}
}

require RUN_MAINTENANCE_IF_MAIN;
