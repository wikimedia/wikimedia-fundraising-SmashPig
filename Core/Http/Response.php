<?php namespace SmashPig\Core\Http;

use SmashPig\Core\Logging\Logger;

class Response {

    protected $killed = false;

    protected $headers = array();
    protected $body = '';

    public function setHeader( $name, $value ) {
        $headers[ $name ] = $value;
    }

    public function setBody( $content ) {
        if ( $this->killed ) {
            Logger::warning( 'Could not set new body content. Output has already been killed.', $content );
            return;
        }

        $this->body = $content;
    }

    public function appendBody( $content ) {
        if ( $this->killed ) {
            Logger::warning( 'Could not set append body content. Output has already been killed.', $content );
            return;
        }

        $this->body .= $content;
    }

    public function killResponse( $code, $reason = null ) {
        if ( $reason === null ) {
            $reason = "HTTP $code: No reason publicly given.";
        }

        header( ':', true, $code );
        $this->setBody( $reason );

        $this->writeResponse();
    }

    public function redirect( $url ) {
        $this->headers[ 'Location' ] = $url;
    }

    public function writeResponse() {
        if ( $this->killed ) {
            Logger::notice( 'Body content already sent. Will not send again.' );
            return;
        }

        $this->killed = true;
        $this->sendHeaders();
        print( $this->body );
    }

    public function sendHeaders() {
        if ( $this->killed ) {
            Logger::notice( 'Body content already sent. Cannot send headers.' );
            return;
        }

        foreach ( $this->headers as $header => $value ) {
            header( $header . ':' . $value, true );
        }
    }

    public function disableOutput() {
        $this->killed = true;
    }
}
