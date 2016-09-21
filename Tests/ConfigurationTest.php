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

	/**
	 * Check that sparsely populated deep structures override one another
	 * additively, and we don't lose branches from half of the union.
	 * TODO: Provide more cases using a dataProvider.
	 */
	public function testOverrideDeep() {
		$config = $this->setConfig();

		$config->override( array(
			'endpoints' => array(
				'listener' => array(
					'class' => 'SmashPig\Ham',
					'postback-url' => 'http://Salad',
				),
			),
		) );

		$config->override( array(
			'endpoints' => array(
				'listener' => array(
					'postback-url' => 'http://Rice',
				),
			),
		) );

		$expected = array(
			'class' => 'SmashPig\Ham',
			'postback-url' => 'http://Rice',
		);

		$this->assertEquals( $expected, $config->val( 'endpoints/listener' ),
			'Deep merge went as hoped' );
	}
}
