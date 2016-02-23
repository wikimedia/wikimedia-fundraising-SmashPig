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
		// but have not yet received notification of capture success.
		Logger::debug( 'Attempting to locate associated message in pending queue.' );
		$pendingQueue = Configuration::getDefaultConfig()->obj( 'data-store/pending' );
		$queueMessage = $pendingQueue->queueGetObject( null, $this->correlationId );
		$success = true;

		if ( $this->shouldCapture( $queueMessage ) ) {
			// Attempt to capture the payment
			$api = new AdyenPaymentsAPI( $this->account );
			Logger::info(
				"Attempting capture API call for currency '{$this->currency}', " .
				"amount '{$this->amount}', reference '{$this->pspReference}'."
			);
			$captureResult = $api->capture( $this->currency, $this->amount, $this->pspReference );

			if ( $captureResult ) {
				// Success!
				Logger::info(
					"Successfully captured payment! Returned reference: '{$captureResult}'. " .
						'Will requeue message as processed.');
				// Remove it from the pending queue
				$pendingQueue->queueAckObject();
				$pendingQueue->removeObjectsById( $this->correlationId );
				// Indicate that it has been captured and re-queue it for use
				// when the capture IPN message comes in.
				$queueMessage->capture_requested = true;
				$pendingQueue->addObj( $queueMessage );
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
		}

		Logger::leaveContext();
		return $success;
	}

	protected function shouldCapture( $queueMessage ) {
		if ( $queueMessage && ( $queueMessage instanceof DonationInterfaceMessage ) ) {
			Logger::debug( 'A valid message was obtained from the pending queue.' );
		} else {
			Logger::warning(
				"Could not find a processable message for PSP Reference '{$this->pspReference}' and correlation ".
					"ID '{$this->correlationId}'.",
				$queueMessage
			);
			return false;
		}
		if ( $queueMessage->capture_requested ) {
			Logger::warning(
				"Duplicate capture job for PSP Reference '{$this->pspReference}' and correlation ".
					"ID '{$this->correlationId}'.",
				$queueMessage
			);
			return false;
		}
		return $this->checkRiskScores( $queueMessage );
	}

	protected function checkRiskScores( DonationInterfaceMessage $queueMessage ) {
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
		$shouldCapture = ( $riskScore < $config->val( 'fraud-filters/risk-threshold' ) );
		$this->sendAntifraudMessage( $queueMessage, $riskScore, $scoreBreakdown, $shouldCapture );
		return $shouldCapture;
	}

	protected function sendAntifraudMessage( $queueMessage, $riskScore, $scoreBreakdown, $shouldCapture ) {
		$action = $shouldCapture ? 'process' : 'review';
		$antifraudMessage = DonationInterfaceAntifraud::factory(
			$queueMessage, $this->merchantReference, $riskScore, $scoreBreakdown, $action
		);
		Logger::debug( "Sending antifraud message with risk score $riskScore and action $action." );
		Configuration::getDefaultConfig()->obj( 'data-store/antifraud' )->addObj( $antifraudMessage );
	}
}
