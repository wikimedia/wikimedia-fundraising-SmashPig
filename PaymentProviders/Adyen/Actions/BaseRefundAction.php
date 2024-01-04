<?php

namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\AdyenMessage;

abstract class BaseRefundAction {

	/**
	 * Map Adyen's fields to ours
	 *
	 * @param AdyenMessage $msg
	 * @return array $queueMsg
	 */
	public function normalizeMessageForQueue( AdyenMessage $msg ): array {
		$queueMsg = [
			'gateway_refund_id' => $msg->pspReference,
			'gateway_parent_id' => $this->getGatewayParentId( $msg ),
			'gross_currency' => $msg->currency,
			'gross' => $msg->amount,
			'date' => strtotime( $msg->eventDate ),
			'gateway' => 'adyen',
			'type' => $this->getTypeForQueueMessage(),
		];
		return $queueMsg;
	}

	/**
	 * Decide on a Gateway Parent ID.
	 *
	 * If the parentPspReference is available on the refund or chargeback $msg then it will be returned.
	 * Otherwise, the pspReference will be returned.
	 *
	 * Note: parentPspReference is not available for SecondChargeback
	 *
	 * @param AdyenMessage $msg The refund or chargeback message object.
	 *
	 * @return string The parent gateway ID for the refund or chargeback.
	 */
	private function getGatewayParentId( AdyenMessage $msg ): string {
		return !empty( $msg->parentPspReference ) ? $msg->parentPspReference : $msg->pspReference;
	}

	abstract protected function getTypeForQueueMessage(): string;
}
