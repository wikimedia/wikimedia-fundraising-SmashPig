<?php namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Context;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Listeners\ListenerDataException;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

/**
 * Responds to payment notifications from dlocal
 */
class DlocalListener extends RestListener {

	protected $paymentStatus = [
		'PAID' => 'SmashPig\PaymentProviders\dlocal\ExpatriatedMessages\PaymentMessage',
	];

	/**
	 * @var \SmashPig\Core\ProviderConfiguration
	 */
	protected $providerConfiguration;

	protected function parseEnvelope( Request $request ) {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();

		// dlocal sends us a json string with the request
		$rawRequest = $request->getRawRequest();
		Logger::info( 'Incoming message (raw): ' . print_r( $rawRequest, true ) );

		// Symfony is losing the auth header somehow
		$headers = getallheaders();

		// Don't store invalid messages.
		if ( !isset( $headers['authorization'] ) ) {
			throw new ListenerDataException( 'INVALID dlocal IPN message with no authorization header: ' . print_r( $rawRequest, true ) );
		}

		$messages = [];

		$decoded = json_decode( $rawRequest, true );
		$status = $decoded['status'];
		if ( array_key_exists( $status, $this->paymentStatus ) ) {
			// add the signature input to the message for the later signature validation
			$login = $this->providerConfiguration->val( 'login' );
			$xdate = $request->headers->get( 'x-date' );
			$signatureInput = $login . $xdate . $rawRequest;
			$decoded['signatureInput'] = $signatureInput;
			$decoded['authorization'] = $headers['authorization'];

			$class = $this->paymentStatus[$status];
			$message = new $class();
			$message->constructFromValues( $decoded );

			$messages[] = $message;
		} else {
			Logger::info( "Message ignored: result = {$status}" );
		}

		return $messages;
	}

	/**
	 * Validate message signature
	 * https://docs.dlocal.com/docs/receive-notifications#signature-of-notifications
	 *
	 * @param ListenerMessage $msg Message object to operate on
	 *
	 * @throws ListenerSecurityException on security violation
	 */
	protected function doMessageSecurity( ListenerMessage $msg ) {
		$secret = $this->providerConfiguration->val( 'secret' );
		$signature = $this->providerConfiguration->object( 'signature-calculator' )->calculate( $msg->signatureInput, $secret );
		// the authorization header from dlocal has this text at the beginning
		$signature = 'V2-HMAC-SHA256, Signature: ' . $signature;
		if ( $msg->authorization != $signature ) {
			throw new ListenerSecurityException();
		}
		return true;
	}

	protected function ackMessage( ListenerMessage $msg ) {
		return true;
	}

	protected function ackEnvelope() {
		// pass
	}
}
