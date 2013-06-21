<?php namespace SmashPig\Core\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Declaration that a class is able to process an HTTP request.
 */
interface IHttpActionHandler {
	/**
	 * Execute an arbitrary action based on the inbound $request object. Additional
	 * specification of what the user requested action is may be found in the $pathParts
	 * parameter.
	 *
	 * @param Request    $request     HTTP request context object
	 * @param Response $response    HTTP response data object
	 * @param array      $pathParts   Any additional action parameters that were part of the URI
	 *
	 * @return Null
	 */
	public function execute( Request $request, Response $response, $pathParts );
}
