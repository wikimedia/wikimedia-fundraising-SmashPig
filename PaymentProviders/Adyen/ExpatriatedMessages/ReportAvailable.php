<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\QueueConsumers\BaseQueueConsumer;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Adyen\Jobs\DownloadReportJob;

class ReportAvailable extends AdyenMessage {
	/**
	 * Will run all the actions that are loaded (from the 'actions' configuration
	 * node) and that are applicable to this message type. Will return true
	 * if all actions returned true. Otherwise will return false. This implicitly
	 * means that the message will be re-queued if any action fails. Therefore
	 * all actions need to be idempotent.
	 *
	 * @returns bool True if all actions were successful. False otherwise.
	 */
	public function runActionChain() {
		Logger::info(
			"Received new report from Adyen: {$this->pspReference}. Generated: {$this->eventDate}.",
			$this->reason
		);

		$jobQueue = BaseQueueConsumer::getQueue( 'jobs-adyen' );
		if ( strpos( $this->pspReference, 'settlement_detail_report' ) === 0 ) {
			$jobObject = DownloadReportJob::factory(
				$this->merchantAccountCode,
				$this->reason
			);
			// FIXME: write queue wrapper to do these next two steps
			$jobArray = json_decode( $jobObject->toJson(), true );
			SourceFields::addToMessage( $jobArray );
			$jobQueue->push( $jobArray );
		} else {
			// We don't know how to handle this report yet
			Logger::notice( "Do not know how to handle report with name '{$this->pspReference}'" );
		}

		return parent::runActionChain();
	}
}
