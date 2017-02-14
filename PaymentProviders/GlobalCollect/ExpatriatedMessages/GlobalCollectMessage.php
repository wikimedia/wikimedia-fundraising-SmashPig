<?php namespace SmashPig\PaymentProviders\GlobalCollect\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\GlobalCollect\Messages\NormalizedMessage;

abstract class GlobalCollectMessage extends ListenerMessage {

	protected function getFieldInfo() {
		$fieldInfo = array();
		foreach ( $this->fields as $key => $info ) {
			// Allow mixed list/hash
			if ( is_numeric( $key ) ) {
				$key = $info;
				$info = array();
			}
			$fieldInfo[$key] = $info;
		}
		return $fieldInfo;
	}

	public function constructFromValues( array $values ) {
		foreach ( $this->getFieldInfo() as $key => $info ) {
			$upperKey = str_replace( '_', '', strtoupper( $key ) );
			$this->$key = ( array_key_exists( $upperKey, $values ) ? $values[$upperKey] : '' );
		}
	}

	/**
	 * Do common normalizations.  Subclasses should perform normalizations
	 * specific to that message type.
	 *
	 * @return array associative queue message thing
	 */
	public function normalizeForQueue() {
		$queueMsg = new NormalizedMessage();

		foreach ( $this->getFieldInfo() as $key => $info ) {
			$destKey = ( array_key_exists( 'map', $info ) ? $info['map'] : $key );
			$queueMsg->$destKey = $this->$key;
		}

		if ( !property_exists( $queueMsg, 'email' ) ) {
			$queueMsg->email = 'nobody@wikimedia.org';
		}
		if ( property_exists( $queueMsg, 'gross' ) ) {
			$queueMsg->gross = round( $queueMsg->gross / 100.0, 2 );
		}

		$queueMsg->gateway = 'globalcollect';
		$queueMsg->correlationId = "{$queueMsg->gateway}-{$queueMsg->gateway_txn_id}";

		return $queueMsg;
	}

	public function validate() {
		return !empty( $this->order_id );
	}

	abstract public function getDestinationQueue();
}
