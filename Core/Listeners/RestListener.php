<?php namespace SmashPig\Core\Listeners;

use SmashPig\Core;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Http\Request;

abstract class RestListener extends ListenerBase {
	public function execute( Request $request, Response $response, $pathParts ) {
		parent::execute( $request, $response, $pathParts );

		Logger::info( "Starting processing of listener request from {$this->request->getClientIp()}" );

		try {
			$this->doIngressSecurity();
			$msgs = $this->parseEnvelope( $request );

			if ( is_array( $msgs ) ) {
				foreach ( $msgs as $msg ) {
					// FIXME: this looks like an elaborate try-catch.  If there's
					// a fatal exception, the remaining messages are toast anyway,
					// so we should... do something different here.
					$this->inflightStore->addObject( $msg );
					if ( $this->processMessage( $msg ) ) {
						$this->inflightStore->removeObjects( $msg );
					}
				}
			}
			$this->ackEnvelope();
		} catch ( ListenerSecurityException $ex ) {
			Logger::notice( 'Message denied by security policy, death is me.', null, $ex );
			$response->setStatusCode( 403, "Not authorized." );
		}
		catch ( ListenerDataException $ex ) {
			Logger::error( 'Listener received request it could not process, death is me.', null, $ex );
			$response->setStatusCode( 500, 'Received data could not be processed.' );
		}
		catch ( Core\ConfigurationException $ex ) {
			Logger::alert( 'Some sad panda gave me a bad configuration.', null, $ex );
			$response->setStatusCode( 500, "Configuration error." );
		}
		catch ( \Exception $ex ) {
			Logger::error( 'Listener threw an unknown exception, death is me.', null, $ex );
			$response->setStatusCode( 500, "Unknown listener exception" );
		}

		Logger::info( 'Finished processing listener request' );
	}

	/**
	 * Parse the web request and turn it into an array of message objects.
	 *
	 * This function should not throw an exception strictly caused by message
	 * contents. If an individual message in the envelope is malformed, this
	 * function should log it and continue as normal.
	 *
	 * @param Request $request Raw web-request
	 *
	 * @throws ListenerConfigException
	 *
	 * @return array of @see Message
	 */
	abstract protected function parseEnvelope( Request $request );

	/**
	 * Perform any required acknowledgement of receipt/status back to the caller.
	 */
	abstract protected function ackEnvelope();
}
