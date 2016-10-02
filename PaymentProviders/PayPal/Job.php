<?php namespace SmashPig\PaymentProviders\PayPal;

use Exception;
use SmashPig\Core\Configuration;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\CrmLink\Messages\SourceFields;

class Job extends RunnableJob {

	static $verifyFailedMsg = 'PayPal message verification failed';

	public $payload;

	/**
	 * @var Configuration
	 */
	protected $config;

	public function execute() {
		$this->config = Configuration::getDefaultConfig();

		// TODO some pending-merge stuff?

		// Verify message with paypal.

		// XXX Why does everything get made into objects?
		$request = (array)$this->payload;

		$valid = $this->config->object( 'api' )->validate( $request );
		if ( ! $valid ) {
			throw new Exception( self::$verifyFailedMsg );
		}

		// Determine message type.

		if ( isset( $request['txn_type'] ) ) {
			$txn_type = $request['txn_type'];
		} elseif (
			isset( $request['payment_status'] ) &&
			in_array( $request['payment_status'], array( 'Reversed', 'Refunded' ) )
		) {
			// refund, chargeback, or reversal
			$txn_type = 'refund';
		} else {
			throw new Exception( 'Invalid PayPal message: ' . json_encode( $request ) );
		}

		$msg_type = null;
		foreach ( $this->config->val( 'messages' ) as $type => $conf ) {
			if ( in_array( $txn_type, $conf['txn_types'] ) ) {
				$msg_type = $type;
			}
		}

		if ( ! $msg_type ) {
			throw new Exception( 'Invalid PayPal message type: ' . $txn_type );
		}

		// Transform into new message.

		// FIXME this could just be an array, but we need compat with
		// keyedopaque* until activemq goes away
		$new_msg = new Message;
		// FIXME hacks because the recurring consumer doesn't want
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

			// FIXME: var map can't put one thing in two places
			if ( isset( $new_msg->contribution_tracking_id ) ) {
				$new_msg->order_id = $new_msg->contribution_tracking_id;
			}

			// FIXME represent special case as var_map config override?
			if ( $msg_type === 'refund' ) {
				$new_msg->gateway_refund_id = $request['txn_id'];
				$new_msg->gross_currency = $request['mc_currency'];
			}

			// FIXME once recurring uses normalized msg it needs this too
			$new_msg->date = strtotime( $new_msg->date );
		}

		$new_msg->gateway = 'paypal';

		// Save to appropriate queue.
		SourceFields::addToMessage( $new_msg );
		$this->config->object( 'data-store/' . $msg_type )
			->push( $new_msg );

		// TODO It would be nice if push() returned something useful so we
		// could return something here too
		return true;
	}
}
