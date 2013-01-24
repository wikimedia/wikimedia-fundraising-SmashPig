<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\PaymentProviders\Adyen\AdyenPaymentsAPI;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Configuration;
use SmashPig\CrmLink\Messages\LimboMessage;
use SmashPig\CrmLink\Messages\PaymentSuccess;

class ProcessCaptureRequestJob extends RunnableJob {

	protected $account;
	protected $currency;
	protected $amount;
	protected $pspReference;

	public static function factory( $correlationId, $account, $currency, $amount, $pspReference ) {
		$obj = new ProcessCaptureRequestJob();

		$obj->correlationId = $correlationId;
		$obj->account = $account;
		$obj->currency = $currency;
		$obj->amount = $amount;
		$obj->pspReference = $pspReference;

		return $obj;
	}

	public function execute() {
		Logger::info(
			"Attempting to capture payment on account '{$this->account}' with reference '{$this->pspReference}' and correlation id '{$this->correlationId}'."
		);

		// Let's do some initial preparation and determine if this is a duplicate or not (ie: does a message exist
		// in the queue for it)
		Logger::debug( "Getting associated message from limbo queue" );
		$limboQueueObj = Configuration::getDefaultConfig()->obj( 'data-store/limbo' );
		$limboMsg = $limboQueueObj->queueGetObject( $this->correlationId );

		if ( $limboMsg && ( $limboMsg instanceof LimboMessage ) ) {
			Logger::debug( "Message obtained from limbo queue" );

			// Capture the payment!
			$api = new AdyenPaymentsAPI( $this->account );
			$result = $api->capture( $this->currency, $this->amount, $this->pspReference );

			if ( $result ) {
				Logger::info( "Successfully captured payment! Returned reference: '{$result}'" );
			} else {
				Logger::error( "Failed to capture payment. Error return was: '{$result}'" );
				return false;
			}

			// Create the payment successful object
			$successMsg = new PaymentSuccess();
			// TODO: Fix this ugly hack
			foreach( get_class_vars( '\SmashPig\CrmLink\Messages\LimboMessage' ) as $key => $value ) {
				$successMsg->$key = $limboMsg->$key;
			}

			// Queue it
			Configuration::getDefaultConfig()->obj( 'data-store/verified' )->addObject( $successMsg );

			// Remove the message and all others
			Logger::info( "Successfully created and queued the success message. Removing all redundant entries." );

			$limboQueueObj->queueAckObject();
			$limboQueueObj->removeObjectsById( $this->correlationId );
		} else {
			Logger::warning(
				"Object in the limbo queue that's not a limbo object? or could not find object with correlation id! Assuming duplicate.",
				$limboMsg
			);
		}

		return true;
	}
}
