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
	const ACTION_PROCESS = 'process'; // all clear to capture payment
	const ACTION_REJECT = 'reject'; // very likely fraud - cancel the authorization
	const ACTION_REVIEW = 'review'; // potential fraud - do not capture now
	const ACTION_DUPLICATE = 'duplicate'; // probable duplicate - cancel the authorization

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
		// this payment has already been sent to the verified queue. If it does,
		// we need to check $capture_requested in case we have requested a capture
		// but have not yet received notification of capture success. Either case can
		// occur when a donor submits their credit card details multiple times against
		// a single order ID. We should cancel all the duplicate authorizations.
		Logger::debug( 'Attempting to locate associated message in pending queue.' );
		/**
		 * @var \SmashPig\Core\DataStores\KeyedOpaqueDataStore
		 */
		$pendingQueue = Configuration::getDefaultConfig()->object( 'data-store/pending' );
		$queueMessage = $pendingQueue->queueGetObject( null, $this->correlationId );
		$success = true;

		$action = $this->determineAction( $queueMessage );
		switch ( $action ) {
			case self::ACTION_PROCESS:
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
							'Marking pending queue message as captured.'
					);
					$pendingQueue->queueAckObject();
					$queueMessage->captured = true;
					$pendingQueue->addObject( $queueMessage );
				} else {
					// Some kind of error in the request. We should keep the pending
					// message, complain loudly, and move this capture job to the
					// damaged queue.
					Logger::error(
						"Failed to capture payment on account '{$this->account}' with reference " .
							"'{$this->pspReference}' and correlation id '{$this->correlationId}'.",
						$queueMessage
					);
					$pendingQueue->queueIgnoreObject();
					$success = false;
				}
				break;
			case self::ACTION_REJECT:
				$this->cancelAuthorization();
				// Delete the fraudy donor details
				$pendingQueue->queueAckObject();
				$pendingQueue->removeObjectsById( $this->correlationId );
				break;
			case self::ACTION_DUPLICATE:
				// We have already captured one payment for this donation attempt, so
				// cancel the duplicate authorization. If there is a pending message,
				// leave it intact for the legitimate RecordCaptureJob.
				$this->cancelAuthorization();
				if ( $queueMessage ) {
					$pendingQueue->queueIgnoreObject();
				}
				break;
			case self::ACTION_REVIEW:
				// Don't capture the payment right now, but leave the donor details in
				// the pending queue in case the authorization is captured via the console.
				$pendingQueue->queueIgnoreObject();
				break;
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
			return self::ACTION_DUPLICATE;
		}
		if ( $queueMessage->captured ) {
			Logger::info(
				"Duplicate PSP Reference '{$this->pspReference}' for correlation ID '{$this->correlationId}'.",
				$queueMessage
			);
			return self::ACTION_DUPLICATE;
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

	protected function cancelAuthorization() {
		Logger::debug( "Cancelling authorization with reference '{$this->pspReference}'" );
		$api = $this->getApi();
		$result = $api->cancel( $this->pspReference );
		if ( $result ) {
			Logger::debug( "Successfully cancelled authorization" );
		} else {
			// Not a big deal
			Logger::warning( "Failed to cancel authorization, it will remain in the payment console" );
		}
	}
}
