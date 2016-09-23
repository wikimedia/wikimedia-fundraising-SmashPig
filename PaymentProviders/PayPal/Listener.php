<?php namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Configuration;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Http\Request;

class Listener implements IHttpActionHandler {

	public function execute( Request $request, Response $response ) {
		$this->config = Configuration::getDefaultConfig();
		// Dump the request right into the queue with no validation.
		$job = new Job;
		$job->payload = $request->getValues();
		$job->{'php-message-class'} = 'SmashPig\PaymentProviders\PayPal\Job';
		$this->config->object( 'data-store/jobs-paypal' )->push( $job );
	}

}