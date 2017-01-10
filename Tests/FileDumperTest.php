<?php

namespace SmashPig\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\QueueConsumers\BaseQueueConsumer;
use SmashPig\Core\QueueConsumers\QueueFileDumper;

class FileDumperTest extends BaseSmashPigUnitTestCase {

	protected $filename;

	public function setUp() {
		parent::setUp();
		Context::initWithLogger( QueueTestConfiguration::instance() );
		$this->filename = tempnam( '/tmp', 'sptest' );
	}

	public function tearDown() {
		parent::tearDown();
		if ( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}
	}

	public function testDump() {
		$queue = BaseQueueConsumer::getQueue( 'test' );
		$expected = '';
		for( $i = 0; $i < 5; $i++ ) {
			$message = array(
				'psycho' => 'alpha',
				'disco' => 'beta',
				'bio' => 'aqua',
				'dooloop' => mt_rand()
			);
			$queue->push( $message );
			$expected .= json_encode( $message ) . "\n";
		}
		$dumper = new QueueFileDumper( 'test', 0, $this->filename );
		$dumper->dequeueMessages();
		$this->assertEquals( $expected, file_get_contents( $this->filename ) );
	}
}
