<?php namespace SmashPig\Core\Http;

class Request {
    public function __construct() {

    }

    public function getRawPostData() {
        return file_get_contents( 'php://input' );
    }
}
