<?php namespace SmashPig\Core\Http;

use SmashPig\Core\Configuration;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Entry point for the base initialized SmashPig application. Expects the requested
 * URL in the format p=<original path>&<original parameters>. The path is required to
 * be in the format of 'configuration view'/'action'/'sub-actions'. IE: adyen/listener
 * or adyen/api/foo/bar/baz.
 *
 * This class will load the requested configuration view; and then look for the action
 * to have been registered under the 'endpoints' node.
 */
class RequestHandler {
	/**
	 * @param null $configPath
	 *
	 * @return Response
	 */
	public static function process( $configPath = null ) {
		// Can go away once we require PHP 5.6
		ini_set( 'default_charset', 'UTF-8' );

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
		$configPath = __DIR__ . '/../../';
		$config = new Configuration(
			$configPath . 'config_defaults.php',
			$configPath . 'config.php',
			$view,
			true
		);
		Logger::init( $config->val( 'logging/root-context' ), $config->val( 'logging/log-level' ), $config );
		Context::init( $config );
		Logger::enterContext( Context::get()->getContextId() );

		if ( $config->nodeExists( 'disabled' ) && $config->val( 'disabled' ) ) {
			Logger::debug( '403 will be given for disabled view.', $uri );
			$response->setStatusCode( 403, "View '$view' disabled. Cannot continue." );
			return $response;			
		}

		if ( $config->nodeExists( 'charset' ) ) {
			// recreate the request with a different input encoding
			// FIXME: This is only converting the POST values.  Also,
			// is there really no better way to do this?
			$decoded = rawurldecode( $request->getContent() );
			$content = mb_convert_encoding( $decoded, 'UTF-8', $config->val( 'charset' ) );

			parse_str( $content, $data );
			$request->request = new ParameterBag( $data );
		}

		set_error_handler( '\SmashPig\Core\Http\RequestHandler::lastChanceErrorHandler' );
		set_exception_handler( '\SmashPig\Core\Http\RequestHandler::lastChanceExceptionHandler' );
		register_shutdown_function('\SmashPig\Core\Http\RequestHandler::shutdownHandler');

		// Check to make sure there's even a point to continuing
		Logger::info( "Starting processing for request, configuration view: '$view', action: '$action'" );
		if ( !$config->nodeExists( "endpoints/$action" ) ) {
			Logger::debug( '403 will be given for unknown action on inbound URL.', $uri );
			$response->setStatusCode( 403, "Action '$action' not configured. Cannot continue." );
			return $response;
		}

		// Inform the request object of our security environment
		$trustedHeader = $config->val( 'security/ip-header-name' );
		if ( $trustedHeader ) {
			$request->setTrustedHeaderName( Request::HEADER_CLIENT_IP, $trustedHeader );
		}
		$trustedProxies = $config->val( 'security/ip-trusted-proxies' );
		if ( $trustedProxies ) {
			$request->setTrustedProxies( $trustedProxies );
		}

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

	public static function shutdownHandler() {
		$lastError = error_get_last();
		if ( $lastError and $lastError['type'] === E_ERROR ) {
			Logger::alert("Fatal error caught by shutdown handler. ({$lastError['type']}) {$lastError['message']} @ {$lastError['file']}:{$lastError['line']}");
		}
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
