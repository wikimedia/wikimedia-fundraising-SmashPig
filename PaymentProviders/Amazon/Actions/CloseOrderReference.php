<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

class CloseOrderReference implements IListenerMessageAction {
	const MESSAGE_CLASS = 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted';

	public function execute( ListenerMessage $msg ) {
		// only close after successful capture
		if ( !( $msg instanceof $this->MESSAGE_CLASS ) ) {
			return true;
		}

		$config = Context::get()->getConfiguration();
		$client = $config->obj( 'payments-client', true );

		$captureId = $msg->getGatewayTransactionId();
		$orderReferenceId = substr( $captureId, 0, 19 );

		Logger::info( "Closing order reference $orderReferenceId" );
		$response = $client->closeOrderReference( array(
			'amazon_order_reference_id' => $orderReferenceId,
		) )->toArray();

		if ( !empty( $response['Error'] ) ) {
			Logger::info(
				"Error losing order reference $orderReferenceId: " .
				$response['Error']['Code'] . ': ' .
				$response['Error']['Message']
			);
			return false;
		}

		return true;
	}
}
