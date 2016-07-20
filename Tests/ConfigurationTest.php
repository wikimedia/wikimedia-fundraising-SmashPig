<?php
namespace SmashPig\Tests;

/**
 */
class ConfigurationTest extends BaseSmashPigUnitTestCase {

	/**
	 * Make sure we throw an exception when overriding a node with a different
	 * type.
	 *
	 * @expectedException SmashPig\Core\SmashPigException
	 * @expectedExceptionMessage Dissimilar types cannot be merged at configuration node map_or_list.
	 *
	 * At integration level because the treeMerge function is currently
	 * protected.
	 */
	public function testTreeMergeDissimilarTypes() {
		$this->setConfig( 'aview', __DIR__ . '/data/dissimilar.yaml' );
		// expectedException above
	}

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
