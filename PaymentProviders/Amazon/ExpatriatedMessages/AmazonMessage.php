<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Amazon\Messages\NormalizedMessage;

abstract class AmazonMessage extends ListenerMessage {

	protected $rawValues = array();

	/**
	 * TODO: We only want to use this for signature validation, perhaps
	 * it can be made more specific to that, especially since Host and URL 
	 * should be pulled from the Request anyway.
	 *
	 * @return array of incoming message params.
	 */
	public function getRawValues() {
		return $this->rawValues;
	}

	public function constructFromValues( array $values ) {
		$this->rawValues = $values;

		foreach ( $this->fields as $key ) {
			$this->$key = (array_key_exists( $key, $values ) ? $values[$key] : '');
		}

		// FIXME: temporary thing to help with dequeuing from "pending"
		$this->correlationId = mt_rand( 100000000, 999999999 );
	}

	/**
	 * Do common normalizations.  Subclasses should perform normalizations
	 * specific to that message type.
	 *
	 * @return array associative queue message thing
	 */
	public function normalizeForQueue() {
		$queueMsg = new NormalizedMessage();

		$queueMsg->gateway = 'amazon';

		return $queueMsg;
	}

	public function getDestinationQueue() {
		//stub
		return null;
	}

	public function validate() {
		//return AmazonAPI::validateSignature( 
		return true;
	}
}
