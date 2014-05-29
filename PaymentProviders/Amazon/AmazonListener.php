<?php namespace SmashPig\PaymentProviders\Amazon;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Logging\Logger;

use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages as MsgDefs;

/**
 * Dispatches incoming messages accoring to type
 *
 * @see https://amazonpayments.s3.amazonaws.com/FPS_ASP_Guides/ASP_Advanced_Users_Guide.pdf
 */
class AmazonListener extends RestListener {
	protected $byTypes = array(
		'TransactionStatus' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\TransactionStatus',
		'TokenCancellation' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\TokenCancellation',
	);

	protected $byStatus = array(
		'PS' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\PaymentSuccess',
		'PF' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\PaymentFailed',
		'PI' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\PaymentInitiated',
		'PR' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\PaymentReversed',
		'RS' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\RefundSuccessful',
		'RF' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\RefundFailed',
		'PaymentSuccess' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\SubscriptionPaymentSuccess',
		'PendingUserAction' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\PendingUserAction',
		'PaymentRescheduled' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\PaymentRescheduled',
		'PaymentCancelled' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\SubscriptionPaymentCancelled',
		'SubscriptionCancelled' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\SubscriptionCancelled',
		'SubscriptionCompleted' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\SubscriptionCompleted',
		'SubscriptionSuccessful' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\SubscriptionSuccessful',
	);

	protected function parseEnvelope( Request $request ) {
		$requestValues = $request->getValues();

		$secureLog = Logger::getTaggedLogger( 'RawData' );
		$secureLog->info( "Incoming message (raw)", $requestValues );

		$messages = array();
		if ( array_key_exists( 'notificationType', $requestValues ) ) {
			$type = $requestValues['notificationType'];
			if ( array_key_exists( $type, $this->byType ) ) {
				$klass = $this->byType[$type];
				$message = new $klass();
				$message->constructFromValues($requestValues);

				$secureLog->debug( "Processed message (normalized)", $message );

				$messages[] = $message;
			} else {
				Logger::info( "Message ignored: notificationType = {$type}" );
			}
		} elseif ( array_key_exists( 'status', $requestValues ) ) {
			$status = $requestValues['status'];
			if ( array_key_exists( $status, $this->byStatus ) ) {
				$klass = $this->byStatus[$status];
				$message = new $klass();
				$message->constructFromValues($requestValues);

				$secureLog->debug( "Processed message (normalized)", $message );

				$messages[] = $message;
			} else {
				Logger::info( "Message ignored: status = {$status}" );
			}
		}

		return $messages;
	}

	/**
	 * Validate message signature
	 *
	 * @param ListenerMessage $msg Message object to operate on
	 *
	 * @throws ListenerSecurityException on security violation
	 */
	protected function doMessageSecurity( ListenerMessage $msg ) {
		if ( !AmazonAPI::verifySignature( $msg->getRawValues() ) ) {
			throw new ListenerSecurityException();
		}
		return true;
	}

	/**
	 * Stub-- maybe this is an egregious pure virtual function
	 */
	protected function ackMessage( ListenerMessage $msg ) {
		return true;
	}

	protected function ackEnvelope() {
		//pass
	}
}
