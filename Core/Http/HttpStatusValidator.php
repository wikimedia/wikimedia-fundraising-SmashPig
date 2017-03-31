<?php

namespace SmashPig\Core\Http;

use SmashPig\Core\Logging\Logger;

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
			case 400:   // Oh noes! Bad request.. BAD CODE, BAD BAD CODE!
				$continue = false;
				Logger::error( "Request returned (400) BAD REQUEST: $body" );
				break;

			case 403:   // Hmm, forbidden? Maybe if we ask it nicely again...
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
			200, // Everything is AWESOME
			201  // Also fine, and we created a thing
		);
	}
}
