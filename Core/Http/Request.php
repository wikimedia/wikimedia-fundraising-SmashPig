<?php namespace SmashPig\Core\Http;

class Request {
	public function __construct() {

	}

	/**
	 * Get post data without interpretation
	 *
	 * @return string
	 */
	public function getRawPostData() {
		return file_get_contents( 'php://input' );
	}

	/**
	 * Return all GET/POST/COOKIE data as an associative array
	 *
	 * @return array
	 */
	public function getValues() {
		return $_REQUEST;
	}
}
