<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Context;

use Symfony\Component\Yaml\Yaml;

$maintClass = 'SmashPig\Maintenance\DumpConfig';

/**
 * Diagnostic tool to print materialized configuration values to stdout.
 */
class DumpConfig extends MaintenanceBase {
	public function execute() {
		$context = Context::get();
		$config = $context->getGlobalConfiguration();
		$values = $config->val( '/' );
		$yaml = Yaml::dump( $values );

		print 'Global config: ' . $yaml;
		$providerConfig = $context->getProviderConfiguration();
		$values = $providerConfig->val( '/' );
		$yaml = Yaml::dump( $values );

		print 'Provider config: ' . $yaml;
	}
}

require RUN_MAINTENANCE_IF_MAIN;
