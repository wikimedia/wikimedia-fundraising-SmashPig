<?php namespace SmashPig\PaymentProviders\Amazon;

use SmashPig\Core\Context;
use SmashPig\Core\SmashPigException;

/**
 * Utility functions for the PayWithAmazon SDK
 */
class AmazonApi {

	public static function createIpnHandler( $headers, $body ) {
		$config = Context::get()->getConfiguration();
		$klass = $config->val( 'ipn-handler-class' );
		return new $klass( $headers, $body );
	}

	public static function findRefundParentId( $refundId ) {
		$config = Context::get()->getConfiguration();
		$client = $config->object( 'payments-client', true );

		// The order reference ID is the first 19 characters of the refund ID
		$orderReferenceId = substr( $refundId, 0, 19 );

		$getDetailsResult = $client->getOrderReferenceDetails( array(
			'amazon_order_reference_id' => $orderReferenceId,
		) )->toArray();
		if ( !empty( $getDetailsResult['Error'] ) ) {
			throw new SmashPigException( $getDetailsResult['Error']['Message'] );
		}

		// The order reference details should contain an IdList with all of the
		// authorizations that have been made against the order reference.  We
		// should only ever have one authorization per order reference.
		$details = $getDetailsResult['GetOrderReferenceDetailsResult']['OrderReferenceDetails'];
		if ( !isset( $details['IdList'] ) || !isset( $details['IdList']['member'] ) ) {
			throw new SmashPigException(
				"No authorizations found for order reference $orderReferenceId!"
			);
		}
		$authorizationIds = (array) $details['IdList']['member'];
		// Check the status of each authorization against the order reference
		foreach ( $authorizationIds as $id ) {
			$authResult = $client->getAuthorizationDetails( array(
				'amazon_authorization_id' => $id,
			) )->toArray();
			if ( !empty( $authResult['Error'] ) ) {
				throw new SmashPigException( $authResult['Error']['Message'] );
			}
			$details = $authResult['GetAuthorizationDetailsResult']['AuthorizationDetails'];
			$state = $details['AuthorizationStatus']['State'];
			// Once we successfully capture payment against an authorization, it
			// transitions to the 'Closed' state. Failed attempts are 'Declined'
			if ( $state === 'Closed' ) {
				// And guess what?  The authorization ID is exactly the same as the
				// capture ID (which we store as the gateway txn id), with one letter
				// changed.
				$captureId = substr( $id, 0, 20 ) . 'C' . substr( $id, 21 );
				return $captureId;
			}
		}
		throw new SmashPigException(
			"No successful authorizations found for order reference $orderReferenceId!"
		);
	}
}
