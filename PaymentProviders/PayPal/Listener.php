<?php namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Configuration;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Logging\Logger;

class Listener implements IHttpActionHandler {

	protected $config;

	public function execute( Request $request, Response $response ) {
		$this->config = Configuration::getDefaultConfig();

		$requestValues = $request->getValues();

		// Don't store blank messages.
		if ( empty( $requestValues ) ) {
			return;
		}

		// Don't store invalid messages.
		$valid = $this->config->object( 'api' )->validate( $requestValues );
		if ( !$valid ) {
			throw new ListenerSecurityException( 'Failed verification' );
		}

		// Dump the request right into the queue with no validation.
		$job = new Job;
		$job->payload = $requestValues;
		$this->config->object( 'data-store/jobs-paypal' )->push( $job );
		Logger::info( 'Pushed new message to jobs-paypal: ' .
			print_r( $requestValues, true ) );
	}

}
