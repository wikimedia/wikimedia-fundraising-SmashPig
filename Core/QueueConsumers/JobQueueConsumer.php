<?php namespace SmashPig\Core\QueueConsumers;

use RuntimeException;
use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\Core\Jobs\RunnableJob;

class JobQueueConsumer extends BaseQueueConsumer {

	protected $successCount = 0;

	/**
	 * Instantiates and runs a job defined by a queue message. Depends on
	 * the base consumer's damaged message store functionality to either
	 * divert messages or stop execution on bad message or job failure.
	 * @param array $jobMessage
	 * @throws \SmashPig\Core\DataStores\DataSerializationException
	 */
	function processMessage( $jobMessage ) {
		if ( !isset( $jobMessage['php-message-class'] ) ) {
			throw new RuntimeException(
				'Job message missing required key \'php-message-class\''
			);
		}

		// TODO: encapsulate the reconstitution step elsewhere.
		$className = $jobMessage['php-message-class'];
		$jsonMessage = json_encode( $jobMessage );
		$jobObj = KeyedOpaqueStorableObject::fromJsonProxy( $className, $jsonMessage );

		if ( $jobObj instanceof Runnable ) {
			if ( !$jobObj->execute() ) {
				throw new RuntimeException(
					'Job tells us that it did not successfully execute. '
					. 'Sending to damaged message store.'
				);
			}
		} else {
			// We don't know how to run this job type.
			throw new RuntimeException(
				get_class( $jobObj ) . ' is not an instance of Runnable. '
				. 'Could not execute and sending to damaged message store.'
			);
		}

		$this->successCount += 1;
	}

	/**
	 * @return int
	 */
	public function getSuccessCount() {
		return $this->successCount;
	}
}
