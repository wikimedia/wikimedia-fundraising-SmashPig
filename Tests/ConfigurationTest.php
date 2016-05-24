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
	}
}
