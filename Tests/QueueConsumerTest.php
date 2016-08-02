<?php

namespace SmashPig\Tests;

use PDO;
use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\DataStores\DamagedDatabase;
use SmashPig\Core\DataStores\QueueConsumer;

class QueueConsumerTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var FifoQueueStore
	 */
	protected $queue;
	/**
	 * @var PDO
	 */
	protected $damaged;

	public function setUp() {
		parent::setUp();
		$this->setConfig( 'default', __DIR__ . '/data/config_queue.yaml' );
		$this->queue = QueueConsumer::getQueue( 'test' );
		$this->queue->createTable( 'test' );
		$this->damaged = DamagedDatabase::get()->getDatabase();

		// Create sqlite schema
		$sql = file_get_contents(
			__DIR__ . '/../Schema/sqlite/002_CreateDamagedTable.sqlite.sql'
		);
		$this->damaged->exec( $sql );
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
			'spookiness' => mt_rand(),
		);
		$this->queue->push( $payload );
		$count = $consumer->dequeueMessages();
		$this->assertEquals( 1, $count, 'Should report 1 message processed' );
		$this->assertEquals( array( $payload ), $processed, 'Bad message' );
		$this->assertNull( $this->queue->pop(),
			'Should delete message when processing is successful'
		);
	}

	public function testDamagedQueue() {
		$payload = array(
			'gateway' => 'test',
			'date' => time(),
			'order_id' => mt_rand(),
			'cousin' => 'itt',
			'kookiness' => mt_rand(),
		);
		$self = $this;
		$ran = false;
		$cb = function( $message ) use ( &$ran, $payload, $self ) {
			$self->assertEquals( $message, $payload );
			$ran = true;
			throw new \Exception( 'kaboom!' );
		};

		$consumer = new QueueConsumer( 'test', $cb, 0, 0 );

		$this->queue->push( $payload );
		try {
			$consumer->dequeueMessages();
		} catch ( \Exception $ex ) {
			$this->fail(
				'Exception should not have bubbled up: ' . $ex->getMessage()
			);
		}
		$this->assertTrue( $ran, 'Callback was not called' );

		$damaged = $this->getDamagedQueueMessage( $payload );
		$this->assertEquals(
			$payload,
			$damaged,
			'Should move message to damaged queue when exception is thrown'
		);
		$this->assertNull(
			$this->queue->pop(),
			'Should delete message on exception when damaged queue exists'
		);
	}

	public function testMessageLimit() {
		$messages = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$message = array(
				'gateway' => 'test',
				'date' => time(),
				'order_id' => mt_rand(),
				'box' => 'thing' . $i,
				'creepiness' => mt_rand(),
			);
			$messages[] = $message;
			$this->queue->push( $message );
		}
		$processedMessages = array();
		$callback = function( $message ) use ( &$processedMessages ) {
			$processedMessages[] = $message;
		};
		// Should work when you pass in the limits as strings.
		$consumer = new QueueConsumer( 'test', $callback, 0, '3' );
		$count = $consumer->dequeueMessages();
		$this->assertEquals( 3, $count, 'dequeueMessages returned wrong count' );
		$this->assertEquals( 3, count( $processedMessages ), 'Called callback wrong number of times' );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertEquals( $messages[$i], $processedMessages[$i], 'Message mutated' );
		}
		$this->assertEquals(
			$messages[3],
			$this->queue->pop(),
			'Messed with too many messages'
		);
	}

	public function testKeepRunningOnDamage() {
		$messages = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$message = array(
				'gateway' => 'test',
				'date' => time(),
				'order_id' => mt_rand(),
				'box' => 'thing' . $i,
				'creepiness' => mt_rand(),
			);
			$messages[] = $message;
			$this->queue->push( $message );
		}
		$processedMessages = array();
		$cb = function( $message ) use ( &$processedMessages ) {
			$processedMessages[] = $message;
			throw new \Exception( 'kaboom!' );
		};

		$consumer = new QueueConsumer( 'test', $cb, 0, 3 );
		$count = 0;
		try {
			$count = $consumer->dequeueMessages();
		} catch ( \Exception $ex ) {
			$this->fail(
				'Exception should not have bubbled up: ' . $ex->getMessage()
			);
		}
		$this->assertEquals( 3, $count, 'dequeueMessages returned wrong count' );
		$this->assertEquals( 3, count( $processedMessages ), 'Called callback wrong number of times' );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertEquals( $messages[$i], $processedMessages[$i], 'Message mutated' );
			$damaged = $this->getDamagedQueueMessage( $messages[$i] );
			$this->assertEquals(
				$messages[$i],
				$damaged,
				'Should move message to damaged queue when exception is thrown'
			);
		}
		$this->assertEquals(
			$messages[3],
			$this->queue->pop(),
			'message 4 should be at the head of the queue'
		);
	}

	protected function getDamagedQueueMessage( $message ) {
		$select = $this->damaged->query( "
			SELECT * FROM damaged
			WHERE gateway='{$message['gateway']}'
			AND order_id = '{$message['order_id']}'" );
		$msg = $select->fetch( PDO::FETCH_ASSOC );
		if ( $msg ) {
			return json_decode( $msg['message'], true );
		}
		return null;
	}

}
