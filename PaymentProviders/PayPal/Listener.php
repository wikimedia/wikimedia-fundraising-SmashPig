<?php namespace SmashPig\PaymentProviders\PayPal;

use RuntimeException;
use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Logging\Logger;

class Listener implements IHttpActionHandler {

	protected $config;

	public function execute( Request $request, Response $response ) {
		$this->config = Configuration::getDefaultConfig();

		$requestValues = $request->getValues();

		// Don't store blank messages.
		if ( empty( $requestValues ) ) {
			return false;
		}

		$valid = false;
		try {
			$valid = $this->config->object( 'api' )->validate( $requestValues );
		} catch ( RuntimeException $e ) {
			// Tried to validate a bunch of times and got nonsense responses.
			Logger::error( $e->getMessage() );
			// 403 should tell them to send it again later.
			$response->setStatusCode( Response::HTTP_FORBIDDEN, 'Failed verification' );
			return false;
		}

		if ( $valid ) {
			$job = new Job;
			$job->payload = $requestValues;
			QueueWrapper::push( 'jobs-paypal', $job );
			Logger::info( 'Pushed new message to jobs-paypal: ' .
				print_r( $requestValues, true ) );
			return true;
		}

		Logger::info( 'INVALID IPN message: ' .  print_r( $requestValues, true ) );
		return false;

	}

}
