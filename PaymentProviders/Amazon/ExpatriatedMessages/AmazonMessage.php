<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Amazon\Messages\NormalizedMessage;

abstract class AmazonMessage extends ListenerMessage {

	protected $gateway_txn_id;
	protected $currency;
	protected $date;
	protected $gross;

	/**
	 * Do common normalizations.  Subclasses should perform normalizations
	 * specific to that message type.
	 *
	 * @return array associative queue message thing
	 */
	public function normalizeForQueue() {
		$queueMsg = array(
			'date' => $this->date,
			'gateway' => 'amazon',
			'gross' => $this->gross
		);
		return $queueMsg;
	}

	public function getDestinationQueue() {
		// stub
		return null;
	}

	public function validate() {
		return true;
	}

	protected function setGatewayIds( $amazonId ) {
		$this->gateway_txn_id = $amazonId;
	}

	public function getOrderReferenceId() {
		return substr( $this->gateway_txn_id, 0, 19);
	}
}
