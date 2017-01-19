<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;

/**
 * Sign Ingenico requests according to instructions at
 * https://developer.globalcollect.com/documentation/api/server/#api-authentication
 */
class Authenticator {

	public function signRequest( OutboundRequest $request ) {
		$config = Context::get()->getConfiguration();

		$toSign = $this->getStringToSign( $request );
		$secretApiKey = $config->val( 'credentials/api-secret' );

		$signature = base64_encode(
			hash_hmac(
				'sha256',
				$toSign,
				$secretApiKey,
				true
			)
		);

		$apiKeyId = $config->val( 'credentials/api-key-id' );

		$authHeader = "GCS v1HMAC:$apiKeyId:$signature";
		$request->setHeader( 'Authorization', $authHeader );
	}

	protected function getStringToSign( OutboundRequest $request ) {
		$headers = $request->getHeaders();

		if ( empty( $headers['Content-Type'] ) ) {
			$contentType = '';
		} else {
			$contentType = $headers['Content-Type'];
		}

		$urlParts = parse_url( $request->getUrl() );
		$pathToSign = $urlParts['path'];
		if ( !empty( $urlParts['query'] ) ) {
			$pathToSign .= '?' . urldecode( $urlParts['query'] );
		}

		$toSign = $request->getMethod() . "\n"
			. $contentType . "\n"
			. $headers['Date'] . "\n"
			//TODO X-GCS headers
			. $pathToSign . "\n";

		return $toSign;
	}
}
