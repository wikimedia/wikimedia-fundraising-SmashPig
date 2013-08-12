<?php namespace SmashPig\Core\Http;

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\AutoLoader;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Entry point for the base initialized SmashPig application. Expects the requested
 * URL in the format p=<original path>&<original parameters>. The path is required to
 * be in the format of 'configuration view'/'action'/'sub-actions'. IE: adyen/listener
 * or adyen/api/foo/bar/baz.
 *
 * This class will load the requested configuration view; and then look for the action
 * to have been registered under the 'endpoints' node.
 *
 * -- AutoLoader Integration --
 * This class will look under the 'namespaces' node for any new namespaces it needs to
 * tell the autoloader about. It is expected that 'namespaces' is an array of arrays.
 * Each subarray is required to have a 'namespace' key and a 'disk-path' key.
 */
class RequestHandler {
	/**
	 * @param null $configPath
	 *
	 * @return Response
	 */
	public static function process( $configPath = null ) {
		// --- Get the request and response objects
		$request = Request::createFromGlobals();
		$response = new Response();
		$response->setPrivate();

		// --- Break the request into parts ---
		$uri = $request->query->get( 'p', '' );
		$parts = explode( '/', $uri );

		if ( count( $parts ) < 2 ) {
			$response->setStatusCode(
				403,
				'Cannot process this request: bad URI format. A configuration node and an action is required'
			);
			return $response;
		}

		$view = array_shift( $parts );
		$action = array_shift( $parts );

		// --- Initialize core services ---
		if ( $configPath === null ) {
			$configPath = AutoLoader::getInstallPath();
		}
		$config = new Configuration(
			AutoLoader::makePath( $configPath, 'config_defaults.php' ),
			AutoLoader::makePath( $configPath, 'config.php' ),
			$view,
			true
		);
		Logger::init( $config->val( 'logging/root-context' ), $config->val( 'logging/log-level' ), $config );
		Context::init();
		Logger::enterContext( Context::get()->getContextId() );

		set_error_handler( '\SmashPig\Core\Http\RequestHandler::lastChanceErrorHandler' );
		set_exception_handler( '\SmashPig\Core\Http\RequestHandler::lastChanceExceptionHandler' );

		// Check to make sure there's even a point to continuing
		if ( !$config->nodeExists( "endpoints/$action" ) ) {
			Logger::debug( '403 will be given for unknown action on inbound URL.', $uri );
			$response->setStatusCode( 403, "Action '$action' not configured. Cannot continue." );
			return $response;
		}

		// Register fun additional things
		AutoLoader::getInstance()->addConfiguredIncludePaths();
		AutoLoader::getInstance()->addConfiguredNamespaces();
		AutoLoader::getInstance()->addConfiguredIncludes();

		// --- Actually get the endpoint object and start the request ---
		$endpointObj = $config->obj( "endpoints/$action" );
		if ( $endpointObj instanceof IHttpActionHandler ) {
			$endpointObj->execute( $request, $response, $parts );
		} else {
			$str = "Requested action '$action' does not implement a known handler. Cannot continue.";
			Logger::debug( $str );
			$response->setStatusCode( 500, $str );
		}

		$code = $response->getStatusCode();
		if ( ( $code !== 200 ) && ( $code !== 302 ) ) {
			$response->setContent( '' );
		}
		return $response;
	}

	public static function lastChanceErrorHandler( $errno, $errstr, $errfile = 'Unknown File',
		$errline = 'Unknown Line', $errcontext = null
	) {
		Logger::alert( "Last chance error handler fired. ($errno) $errstr @ $errfile:$errline", $errcontext );

		$response = new Response();
		$response->setPrivate();
		$response->setStatusCode( 500, "Unhandled internal server error." );
		$response->send();

		return false;
	}

	/**
	 * Hook from set_exception_handler(). Will clear output data, set the HTTP status to 500: Internal Error
	 * and then die.
	 *
	 * @param \Exception $ex The uncaught exception
	 */
	public static function lastChanceExceptionHandler( $ex ) {
		Logger::alert( "Last chance exception handler fired.", null, $ex );

		$response = new Response();
		$response->setPrivate();
		$response->setStatusCode( 500, "Unhandled internal server exception." );
		$response->send();
	}
}
