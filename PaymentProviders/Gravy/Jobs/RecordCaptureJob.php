<?php namespace SmashPig\PaymentProviders\Gravy\Jobs;

use SmashPig\Core\Runnable;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\GravyMessage;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

/**
 * Job that sends a Transaction Webhook message from Gravy into the donations queue.
 *
 * Class TransactionMessageJob
 *
 * @package SmashPig\PaymentProviders\Gravy\Jobs
 */
class RecordCaptureJob implements Runnable {

	public array $payload;

	public static function factory( GravyMessage $message, PaymentDetailResponse $transactionDetails ): array {
		return [
			'class' => self::class,
			'payload' => array_merge(
				[
					"eventDate" => $message->getMessageDate()
				], $transactionDetails->getNormalizedResponse()
			)
		];
	}

	public function execute() {
		// TODO: Do  Record capture flow
	}
}
