<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted;

class CloseOrderReference implements IListenerMessageAction {

	public function execute( ListenerMessage $msg ) {
		// only close after successful capture
		if ( !( $msg instanceof CaptureCompleted ) ) {
			return true;
		}

		$config = Context::get()->getConfiguration();
		$client = $config->object( 'payments-client', true );

		$orderReferenceId = $msg->getOrderReferenceId();

		Logger::info( "Closing order reference $orderReferenceId" );
		$response = $client->closeOrderReference( array(
			'amazon_order_reference_id' => $orderReferenceId,
		) )->toArray();

		if ( !empty( $response['Error'] ) ) {
			Logger::info(
				"Error closing order reference $orderReferenceId: " .
				$response['Error']['Code'] . ': ' .
				$response['Error']['Message']
			);
			return false;
		}

		return true;
	}
}
