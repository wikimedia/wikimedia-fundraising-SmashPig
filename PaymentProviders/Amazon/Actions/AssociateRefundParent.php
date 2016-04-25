<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\SmashPigException;
use SmashPig\PaymentProviders\Amazon\AmazonApi;

/**
 * Associate refunds with their parent contribution
 */
class AssociateRefundParent implements IListenerMessageAction {
	const MESSAGE_CLASS = 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\RefundCompleted';

	public function execute( ListenerMessage $msg ) {
		// Bail out if not a refund
		if ( get_class( $msg ) !== self::MESSAGE_CLASS ) {
			return true;
		}
		$refundId = $msg->getGatewayTransactionId();
		Logger::info( "Looking up ID of original transaction for refund $refundId" );
		try {
			$parentId = AmazonApi::findRefundParentId( $refundId );
			$msg->setParentId( $parentId );
			return true;
		} catch ( SmashPigException $ex ) {
			Logger::error( $ex->getMessage() );
			return false;
		}
	}
}
