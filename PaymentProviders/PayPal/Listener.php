<?php namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\DataStores\QueueFactory;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Http\Request;

class Listener implements IHttpActionHandler {

	public function execute( Request $request, Response $response ) {
		$requestValues = $request->getValues();

		// Don't store blank messages.
		if ( empty( $requestValues ) ) {
			return;
		}

		// Dump the request right into the queue with no validation.
		$job = new Job;
		$job->payload = $requestValues;
		$job->{'php-message-class'} = 'SmashPig\PaymentProviders\PayPal\Job';
		QueueFactory::getQueue( 'jobs-paypal' )->push( $job );
	}

}
