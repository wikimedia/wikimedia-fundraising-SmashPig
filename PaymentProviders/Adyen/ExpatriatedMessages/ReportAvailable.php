<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessAccountingReportJob;

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

		$jobQueueObj = Context::get()->getConfiguration()->obj( 'data-store/jobs' );
		if ( strpos( $this->pspReference, 'payments_accounting_report' ) === 0 ) {
			Logger::info( 'DISABLED: Not doing anything with nightly audit file, see bug T117451' );
			/**
			 * $jobQueueObj->addObject(
			 * 	ProcessAccountingReportJob::factory(
			 * 		$this->merchantAccountCode,
			 * 		$this->reason
			 * 	)
			 * );
			 */
		} else {
			// We don't know how to handle this report yet
			Logger::notice( "Do not know how to handle report with name '{$this->pspReference}'" );
		}

		return parent::runActionChain();
	}
}
