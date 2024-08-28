<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\RefundMessage;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class RefundAction extends GravyAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'RefundAction' );
		$refundDetails = $this->getRefundDetails( $msg );

		if ( $refundDetails->isSuccessful() ) {
			$message = $refundDetails->getNormalizedResponse();
			unset( $message['raw_response'] );
			$message['date'] = strtotime( $msg->getMessageDate() );

			// Gravy sends a 'processing' notification following a refund request. Once complete
			// at the backend processor, they send a subsequent 'succeeded' notification which is our
			// signal to update the record in CiviCRM.
			if ( $refundDetails->getStatus() === FinalStatus::COMPLETE ) {
				QueueWrapper::push( 'refund', $message );
			} else {
				$tl->info( "Skipping in-progress refund notification for refund {$refundDetails->getGatewayRefundId()}" );
			}
		} else {
			$tl->info( "Problem locating refund with refund id {$refundDetails->getGatewayRefundId()}" );
		}

		return true;
	}

	public function getRefundDetails( RefundMessage $msg ): RefundPaymentResponse {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$provider = $providerConfiguration->object( 'payment-provider/cc' );

		$refundDetails = $provider->getRefundDetails( [
			"gateway_refund_id" => $msg->getGatewayRefundId()
		] );

		return $refundDetails;
	}
}
