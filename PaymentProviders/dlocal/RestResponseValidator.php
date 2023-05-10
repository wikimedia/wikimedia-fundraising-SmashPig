<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Http\HttpStatusValidator;
use SmashPig\Core\Logging\Logger;
use Symfony\Component\HttpFoundation\Response;

class RestResponseValidator extends HttpStatusValidator {
	/**
	 * Stop retrying authorizations when a response status code of 403 is received
	 * See T335419
	 * @param array $parsedResponse
	 * @return bool
	 */
	public function shouldRetry( array $parsedResponse ): bool {
		if ( $parsedResponse['status'] === Response::HTTP_FORBIDDEN ) {
			$body = $parsedResponse['body'];
			Logger::warning( "Request returned (403) FORBIDDEN: $body" );
			return false;
		}
		return parent::shouldRetry( $parsedResponse );
	}
}
