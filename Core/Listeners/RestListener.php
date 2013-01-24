<?php namespace SmashPig\Core\Listeners;

use SmashPig\Core;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;

abstract class RestListener extends ListenerBase {
    public function execute( Request $request, Response $response, $pathParts ) {
        Logger::enterContext( 'sess' . mt_rand( 100000000, 999999999 ) );
        Logger::info( "Starting processing of listener request from {$_SERVER['REMOTE_ADDR']}" );

        try {
            $this->doIngressSecurity();
            $msgs = $this->parseEnvelope( $request );

            if ( is_array( $msgs ) ) {
                foreach ( $msgs as $msg ) {
                    $this->pendingStore->add_message( $msg );
                    if ( $this->processMessage( $msg ) ) {
                        $this->pendingStore->remove_message( $msg );
                    }
                }
            }
            $this->ackEnvelope();
        } catch ( ListenerSecurityException $ex ) {
            Logger::notice( 'Message denied by security policy, death is me.', null, $ex );
            $response->killResponse( 403 );
        }
        catch ( ListenerDataException $ex ) {
            Logger::error( 'Listener received request it could not process, death is me.', null, $ex );
            $response->killResponse( 500, 'Received data could not be processed.' );
        }
        catch ( Core\ConfigurationException $ex ) {
            Logger::alert( 'Some sad panda gave me a bad configuration.', null, $ex );
            $response->killResponse( 500 );
        }
        catch ( \Exception $ex ) {
            Logger::error( 'Listener threw an unknown exception, death is me.', null, $ex );
            $response->killResponse( 500 );
        }

        Logger::info( 'Finished processing listener request' );
    }

    /**
     * Parse the raw data from the web request and turn it into an array of message objects. This
     * function should not return an exception unless the configuration data is malformed. If an
     * individual message element in the envelope is malformed this function should log it and
     * continue as normal.
     *
     * @param string $data Raw web-request data
     *
     * @throws ListenerConfigException
     * @throws ListenerDataException
     *
     * @return mixed Array of @see Message
     */
    abstract protected function parseEnvelope( Request $request );

    /**
     * Perform any required acknowledgement of receipt/status back to the caller.
     */
    abstract protected function ackEnvelope();
}
