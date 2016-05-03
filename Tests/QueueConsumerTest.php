<?php

namespace SmashPig\Tests;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueConsumer;

class QueueConsumerTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var FifoQueueStore
	 */
	protected $queue;

	public function setUp() {
		parent::setUp();
		$this->setConfig( 'default', __DIR__ . '/data/config_queue.yaml' );
		$this->queue = Context::get()->getConfiguration()->object( 'data-store/test' );
		$this->queue->createTable( 'test' );
	}

	public function testEmptyQueue() {
		$noOp = function( $unused ) {};
		$consumer = new QueueConsumer( 'test', $noOp );
		$count = $consumer->dequeueMessages();
		$this->assertEquals( 0, $count, 'Should report 0 messages processed' );
	}

	public function testOneMessage() {
		$processed = array();
		$cb = function( $message ) use ( &$processed ) {
			$processed[] = $message;
		};
		$consumer = new QueueConsumer( 'test', $cb );
		$payload = array(
			'wednesday' => 'addams',
			'rand' => mt_rand(),
		);
		$this->queue->push( $payload );
		$count = $consumer->dequeueMessages();
		$this->assertEquals( 1, $count, 'Should report 1 message processed' );
		$this->assertEquals( array( $payload ), $processed, 'Bad message' );
		$this->assertNull(
			$this->queue->popAtomic( function( $unused ) {} ),
			'Should delete message when processing is successful'
		);
	}

	public function testRollBack() {
		$payload = array(
			'uncle' => 'fester',
			'watts' => mt_rand(),
		);
		$self = $this;
		$ran = false;
		$cb = function( $message ) use ( &$ran, $payload, $self ) {
			$self->assertEquals( $message, $payload );
			$ran = true;
			throw new \Exception( 'kaboom!' );
		};
		$consumer = new QueueConsumer( 'test', $cb );
		$this->queue->push( $payload );
		try {
			$consumer->dequeueMessages();
			$this->fail( 'Exception should have bubbled up' );
		} catch ( \Exception $ex ) {
			$this->assertEquals( 'kaboom!', $ex->getMessage(), 'Exception mutated' );
		}
		$this->assertEquals(
			$payload,
			$this->queue->popAtomic( function( $unused ) {} ),
			'Should not delete message when exception is thrown'
		);
	}
}
