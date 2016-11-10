<?php namespace SmashPig\PaymentProviders\Amazon;

use PayWithAmazon\IpnHandlerInterface;
use PayWithAmazon\PaymentsClientInterface;
use SmashPig\Core\Context;
use SmashPig\Core\SmashPigException;

/**
 * Utility functions for the PayWithAmazon SDK
 */
class AmazonApi {

	/**
	 * @var PaymentsClientInterface
	 */
	protected $client;

	/**
	 * @var AmazonApi
	 */
	protected static $instance;

	private function __construct() {
		$config = Context::get()->getConfiguration();
		$this->client = $config->object( 'payments-client', true );
	}

	public static function get() {
		if ( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @param array $headers Associative array of HTTP headers
	 * @param string $body HTTP request body (should be JSON-encoded)
	 * @return IpnHandlerInterface
	 */
	public static function createIpnHandler( $headers, $body ) {
		$config = Context::get()->getConfiguration();
		$klass = $config->val( 'ipn-handler-class' );
		return new $klass( $headers, $body );
	}

	/**
	 * @param string $orderReferenceId
	 * @return string Amazon's ID for the first successful capture associated
	 *  with this order reference
	 * @throws SmashPigException
	 */
	public function findCaptureId( $orderReferenceId ) {
		// The order reference details should contain an IdList with all of the
		// authorizations that have been made against the order reference.  We
		// should only ever have one authorization per order reference.
		$details = $this->getOrderReferenceDetails( $orderReferenceId );

		if ( !isset( $details['IdList'] ) || !isset( $details['IdList']['member'] ) ) {
			throw new SmashPigException(
				"No authorizations found for order reference $orderReferenceId!"
			);
		}
		$authorizationIds = (array) $details['IdList']['member'];
		// Check the status of each authorization against the order reference
		foreach ( $authorizationIds as $id ) {
			$authResult = $this->client->getAuthorizationDetails( array(
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

	/**
	 * @param string $orderReferenceId 19 character Amazon order ID
	 * @return array OrderReferenceDetails as an associative array
	 *  @see https://payments.amazon.com/documentation/apireference/201752660
	 * @throws SmashPigException
	 */
	protected function getOrderReferenceDetails( $orderReferenceId ) {
		$getDetailsResult = $this->client->getOrderReferenceDetails(
			array(
				'amazon_order_reference_id' => $orderReferenceId,
			)
		)->toArray();
		if ( !empty( $getDetailsResult['Error'] ) ) {
			throw new SmashPigException( $getDetailsResult['Error']['Message'] );
		}
		return $getDetailsResult['GetOrderReferenceDetailsResult']['OrderReferenceDetails'];
	}
}
