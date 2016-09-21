<?php namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Configuration;
use SmashPig\Core\Jobs\RunnableJob;

class Job extends RunnableJob {

	static $verifyFailedMsg = 'PayPal message verification failed';

	public $payload;

	public function execute() {
		$this->config = Configuration::getDefaultConfig();

		// TODO some pending-merge stuff?

		// Verify message with paypal.

		// XXX Why does everything get made into objects?
		$request = (array)$this->payload;

		$valid = $this->config->object( 'api' )->validate( $request );
		if ( ! $valid ) {
			throw new \Exception( self::$verifyFailedMsg );
		}

		// Determine message type.

		$txn_type = $request['txn_type'];

		$msg_type = null;
		foreach ( $this->config->val( 'messages' ) as $type => $conf ) {
			if ( in_array( $txn_type, $conf['txn_types'] ) ) {
				$msg_type = $type;
			}
		}

		if ( ! $msg_type ) {
			throw new \Exception( 'Invalid PayPal message type: ' . $txn_type );
		}

		// Transform into new message.

		// FIXME this could just be an array, but we need compat with
		// keyedopaque* until activemq goes away
		$new_msg = new Message;
		// FIXME hack because the recurring consumer doesn't want
		// a normalized message
		if ( $msg_type === 'recurring' ) {
			foreach ( $request as $key => $val ) {
				$new_msg->$key = $val;
			}
		} else {
			$map = $this->config->val( 'var_map' );
			foreach ( $map as $rx => $tx ) {
				if ( array_key_exists( $rx, $request ) ) {
					$new_msg->$tx = $request[$rx];
				}
			}

			// FIXME represent special case as var_map config override?
			if ( $msg_type === 'refund' ) {
				$new_msg->gateway_refund_id = $request['txn_id'];
			}
		}

		// hax
		$new_msg->date = strtotime( $new_msg->date );
		$new_msg->gateway = 'paypal';

		// Save to appropriate queue.

		$this->config->object( 'data-store/' . $msg_type )
			->push( $new_msg );

		// TODO It would be nice if push() returned something useful so we
		// could return something here too
		return true;
	}
}
