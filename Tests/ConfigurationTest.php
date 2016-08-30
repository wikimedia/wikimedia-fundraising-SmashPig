<?php
namespace SmashPig\Tests;

/**
 */
class ConfigurationTest extends BaseSmashPigUnitTestCase {

	public function testOverride() {
		$config = $this->setConfig();

		$this->assertEquals( 'SmashPig', $config->val( 'logging/root-context' ),
			'Default config was as expected.' );
		$config->override( array(
			'logging' => array(
				'root-context' => 'FOO',
			),
		) );
		$this->assertEquals( 'FOO', $config->val( 'logging/root-context' ),
			'Config was overridden.' );
	}
}
