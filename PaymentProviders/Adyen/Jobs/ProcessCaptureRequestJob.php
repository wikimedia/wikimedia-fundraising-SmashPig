<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Configuration;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\Core\Logging\Logger;
use SmashPig\CrmLink\Messages\DonationInterfaceAntifraud;
use SmashPig\CrmLink\Messages\DonationInterfaceMessage;
use SmashPig\PaymentProviders\Adyen\AdyenPaymentsAPI;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;

/**
 * Job that checks authorization IPN messages from Adyen and requests payment
 * capture if not yet processed and if the risk score is below our threshold.
 *
 * Class ProcessCaptureRequestJob
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class ProcessCaptureRequestJob extends RunnableJob {

	protected $account;
	protected $currency;
	protected $amount;
	protected $merchantReference;
	protected $pspReference;
	protected $avsResult;
	protected $cvvResult;
	// Actions to take after examining capture request and queue message
	const ACTION_IGNORE = 'ignore'; // no donor info or auth already captured, do nothing
	const ACTION_PROCESS = 'process'; // all clear to capture payment
	const ACTION_REJECT = 'reject'; // very likely fraud - cancel the authorization
	const ACTION_REVIEW = 'review'; // potential fraud - do not capture now

	public static function factory( Authorisation $authMessage ) {
		$obj = new ProcessCaptureRequestJob();

		$obj->correlationId = $authMessage->correlationId;
		$obj->account = $authMessage->merchantAccountCode;
		$obj->currency = $authMessage->currency;
		$obj->amount = $authMessage->amount;
		$obj->merchantReference = $authMessage->merchantReference;
		$obj->pspReference = $authMessage->pspReference;
		$obj->cvvResult = $authMessage->cvvResult;
		$obj->avsResult = $authMessage->avsResult;

		return $obj;
	}

	public function execute() {
		Logger::enterContext( "corr_id-{$this->correlationId}" );
		Logger::info(
			"Running capture request job on account '{$this->account}' with reference '{$this->pspReference}' " .
			"and correlation id '{$this->correlationId}'."
		);

		// Determine if a message exists in the pending queue; if it does not then
		// this payment has already been sent to the verified queue.
		Logger::debug( 'Attempting to locate associated message in pending queue.' );
		/**
		 * @var \SmashPig\Core\DataStores\KeyedOpaqueDataStore
		 */
		$pendingQueue = Configuration::getDefaultConfig()->object( 'data-store/pending' );
		$queueMessage = $pendingQueue->queueGetObject( null, $this->correlationId );
		$success = true;

		$action = $this->determineAction( $queueMessage );
		if ( $action == self::ACTION_PROCESS ) {
			// Tell the pending queue to keep the message around for the RecordCaptureJob
			$pendingQueue->queueIgnoreObject();

			// Attempt to capture the payment
			$api = $this->getApi();
			Logger::info(
				"Attempting capture API call for currency '{$this->currency}', " .
				"amount '{$this->amount}', reference '{$this->pspReference}'."
			);
			$captureResult = $api->capture( $this->currency, $this->amount, $this->pspReference );

			if ( $captureResult ) {
				// Success!
				Logger::info(
					"Successfully captured payment! Returned reference: '{$captureResult}'. " .
						'Leaving pending message in queue for record capture job.');
			} else {
				// Some kind of error in the request. We should keep the pending
				// message, complain loudly, and move this capture job to the
				// damaged queue.
				Logger::error(
					"Failed to capture payment on account '{$this->account}' with reference " .
						"'{$this->pspReference}' and correlation id '{$this->correlationId}'.",
					$queueMessage
				);
				$success = false;
			}
		} else if ( $action == self::ACTION_REJECT ) {
			Logger::debug( "Cancelling authorization with reference '{$this->pspReference}'" );
			$api = $this->getApi();
			$result = $api->cancel( $this->pspReference );
			if ( $result ) {
				Logger::debug( "Successfully cancelled authorization" );
			} else {
				// Not a big deal
				Logger::warning( "Failed to cancel authorization, it will remain in the payment console" );
			}
			// Delete the queue message whatever happened
			$pendingQueue->queueAckObject();
			$pendingQueue->removeObjectsById( $this->correlationId );
		} else {
			// Not cancelling, just leaving the authorization in the console for review.
			// Put the donor details back on the pending queue.
			$pendingQueue->queueIgnoreObject();
		}

		Logger::leaveContext();
		return $success;
	}

	protected function determineAction( $queueMessage ) {
		if ( $queueMessage && ( $queueMessage instanceof DonationInterfaceMessage ) ) {
			Logger::debug( 'A valid message was obtained from the pending queue.' );
		} else {
			Logger::warning(
				"Could not find a processable message for PSP Reference '{$this->pspReference}' and correlation ".
					"ID '{$this->correlationId}'.",
				$queueMessage
			);
			return self::ACTION_IGNORE;
		}
		return $this->getRiskAction( $queueMessage );
	}

	protected function getRiskAction( DonationInterfaceMessage $queueMessage ) {
		$config = Configuration::getDefaultConfig();
		$riskScore = $queueMessage->risk_score ? $queueMessage->risk_score : 0;
		Logger::debug( "Base risk score from payments site is $riskScore, " .
			"raw CVV result is '{$this->cvvResult}' and raw AVS result is '{$this->avsResult}'." );
		$cvvMap = $config->val( 'fraud-filters/cvv-map' );
		$avsMap = $config->val( 'fraud-filters/avs-map' );
		$scoreBreakdown = array();
		if ( array_key_exists( $this->cvvResult, $cvvMap ) ) {
			$scoreBreakdown['getCVVResult'] = $cvvScore = $cvvMap[$this->cvvResult];
			Logger::debug( "CVV result '{$this->cvvResult}' adds risk score $cvvScore." );
			$riskScore += $cvvScore;
		} else {
			Logger::warning( "CVV result '{$this->cvvResult}' not found in cvv-map.", $cvvMap );
		}
		if ( array_key_exists( $this->avsResult, $avsMap ) ) {
			$scoreBreakdown['getAVSResult'] = $avsScore = $avsMap[$this->avsResult];
			Logger::debug( "AVS result '{$this->avsResult}' adds risk score $avsScore." );
			$riskScore += $avsScore;
		} else {
			Logger::warning( "AVS result '{$this->avsResult}' not found in avs-map.", $avsMap );
		}
		$action = self::ACTION_PROCESS;
		if ( $riskScore >= $config->val( 'fraud-filters/review-threshold' ) ) {
			$action = self::ACTION_REVIEW;
		}
		if ( $riskScore >= $config->val( 'fraud-filters/reject-threshold' ) ) {
			$action = self::ACTION_REJECT;
		}
		$this->sendAntifraudMessage( $queueMessage, $riskScore, $scoreBreakdown, $action );
		return $action;
	}

	protected function sendAntifraudMessage( $queueMessage, $riskScore, $scoreBreakdown, $action ) {
		$antifraudMessage = DonationInterfaceAntifraud::factory(
			$queueMessage, $this->merchantReference, $riskScore, $scoreBreakdown, $action
		);
		Logger::debug( "Sending antifraud message with risk score $riskScore and action $action." );
		Configuration::getDefaultConfig()->object( 'data-store/antifraud' )->addObject( $antifraudMessage );
	}

	/**
	 * @return \SmashPig\PaymentProviders\Adyen\AdyenPaymentsInterface
	 */
	protected function getApi() {
		$api = Configuration::getDefaultConfig()->object( 'payment-provider/adyen/api' );
		$api->setAccount( $this->account );
		return $api;
	}
}
