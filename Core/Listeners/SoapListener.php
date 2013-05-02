<?php namespace SmashPig\Core\Listeners;

use SmashPig\Core;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;

abstract class SoapListener extends ListenerBase {

	/** @var \SoapServer Request processing object */
	protected $server;

	/** @var string URI to the WDSL defining the commands available in this server */
	protected $wsdlpath = '';

	/** @var array Mapping of WSDL entity names to PHP classes. Key is the entity name, value is the fully defined class name */
	protected $classmap = array();

	public function __construct() {
		parent::__construct();
		$this->server = new \SoapServer(
			$this->wsdlpath,
			array(
				 'classmap'   => $this->classmap,
				 'cache_wsdl' => WSDL_CACHE_BOTH,
			)
		);
	}

	public function execute( Request $request, Response $response, $pathParts ) {
		Logger::enterContext( 'sess' . mt_rand( 100000000, 999999999 ) );
		Logger::info( "Starting processing of listener request from {$_SERVER[ 'REMOTE_ADDR' ]}" );

		try {
			$this->doIngressSecurity();

			$soapData = $request->getRawPostData();
			Logger::enterContext( 'RawData' );
			Logger::info( 'Received SOAP data', $soapData );
			Logger::leaveContext();

			$response->sendHeaders();

			/* --- Unfortunately because of how PHP handles SOAP requests we cannot do the fully wrapped
					loop like we could in the REST listener. Instead it is up to the listener itself to
					do the required calls to $this->pendingStore->addObject( $msg ), $this->processMessage( $msg ),
					and $this->pendingStore->removeObject( $msg ).

					It is also expected that inside the handle() context that an exception will throw a SOAP
					fault through $this->server->fault() instead of doing a $response->kill_response() call.
			*/
			$this->server->setObject( $this );
			$this->server->handle( $soapData );

			/* We disable output late in the game in case there was a last minute exception that could
				be handled by the SOAP listener object inside the handle() context. */
			$response->disableOutput();
		} catch ( ListenerSecurityException $ex ) {
			Logger::notice( 'Message denied by security policy, death is me.', null, $ex );
			$response->killResponse( 403 );
		}
		catch ( \Exception $ex ) {
			Logger::error( 'Listener threw an unknown exception, death is me.', null, $ex );
			$response->killResponse( 500 );
		}

		Logger::info( 'Finished processing listener request' );
	}
}
