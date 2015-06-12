<?php namespace SmashPig\PaymentProviders\Astropay;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Listeners\ListenerDataException;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Logging\Logger;

/**
 * Responds to payment messages from Astropay
 */
class AstropayListener extends RestListener {

	protected $byResult = array(
		'9' => 'SmashPig\PaymentProviders\Astropay\ExpatriatedMessages\PaymentMessage',
		'8' => 'SmashPig\PaymentProviders\Astropay\ExpatriatedMessages\ExpirationMessage',
	);

	protected function parseEnvelope( Request $request ) {
		$requestValues = $request->getValues();

		$secureLog = Logger::getTaggedLogger( 'RawData' );
		$secureLog->info( "Incoming message (raw)", base64_encode( $request->getRawRequest() ) );

		$messages = array();

		// Can't even check signature without these four
		$required = array( 'result', 'x_amount', 'x_invoice', 'x_control' );
		$missing = array_diff( $required, array_keys( $requestValues ) );
		if ( count( $missing ) ) {
			$list = implode( ',', $missing );
			throw new ListenerDataException( "Astropay message missing required key(s) $list." );
		}

		$result = $requestValues['result'];
		if ( array_key_exists( $result, $this->byResult ) ) {
			$klass = $this->byResult[$result];
			$message = new $klass();
			$message->constructFromValues( $requestValues );

			$secureLog->debug( "Found message ", $message );

			$messages[] = $message;
		} else {
			Logger::info( "Message ignored: result = {$result}" );
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
		$login = $this->c->val( 'login' );
		$secret = $this->c->val( 'secret' );
		$signed = $login . $msg->getSignedString();
		$control = strtoupper(
			hash_hmac( 'sha256', pack( 'A*', $signed ), pack( 'A*', $secret ) )
		);

		if ( $control != $msg->getSignature() ) {
			throw new ListenerSecurityException();
		}
		return true;
	}

	protected function ackMessage( ListenerMessage $msg ) {
		return true;
	}

	protected function ackEnvelope() {
		//pass
	}
}
