<?php

namespace SmashPig\Core\Http;

use SmashPig\Core\Logging\Logger;
use Symfony\Component\HttpFoundation\Response;

/**
 * Determines whether to retry HTTP requests based on status code
 */
class HttpStatusValidator implements ResponseValidator {

	public function shouldRetry( $parsedResponse ) {

		$statusCode = $parsedResponse['status'];
		if ( array_search( $statusCode, $this->getSuccessCodes() ) !== false ) {
			Logger::debug( "Successful request" );
			return false;
		}
		$body = $parsedResponse['body'];

		switch ( $statusCode ) {
			case Response::HTTP_BAD_REQUEST:   // Oh noes! Bad request.. BAD CODE, BAD BAD CODE!
				$continue = false;
				Logger::error( "Request returned (400) BAD REQUEST: $body" );
				break;

			case Response::HTTP_FORBIDDEN:   // Hmm, forbidden? Maybe if we ask it nicely again...
				$continue = true;
				Logger::alert( "Request returned (403) FORBIDDEN: $body" );
				break;

			default:    // No clue what happened... break out and log it
				$continue = false;
				Logger::error( "Request returned http status ($statusCode): $body" );
				break;
		}
		return $continue;
	}

	protected function getSuccessCodes() {
		return array(
			Response::HTTP_OK, // Everything is AWESOME
			Response::HTTP_CREATED  // Also fine, and we created a thing
		);
	}
}
