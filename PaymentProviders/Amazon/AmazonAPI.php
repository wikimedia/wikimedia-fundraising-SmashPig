<? namespace SmashPig\PaymentProviders\Amazon;

// TODO we are currently ignoring signatures ;)
// ... this implementation is very not functional
class AmazonAPI {
	static public function verifySignature( $values ) {
		return true;
		// FUBAR

		$incomingSignature = $values['signature'];
		unset( $values['signature'] );
		$incomingParams = http_build_query( $values, '', '&' );

		$requestParams = array(
			'Action' => "VerifySignature",
			'HttpParameters' => $incomingParams,
			'SignatureMethod' => "HmacSHA256",
			'SignatureVersion' => "2",
			'Timestamp' => date( 'c' ),
			'Version' => "2010-08-28",
		);

		ksort( $requestParams );

		$secret_key = $this->account_config[ "SecretKey" ];

		$query_str = $this->encodeQuery( $params );
		$path_encoded = str_replace( "%2F", "/", rawurlencode( $path ) );

		$message = "GET\n{$host}\n{$path_encoded}\n{$query_str}";

		$signature = self::signRequest( $parsed_uri[ 'host' ], $parsed_uri[ 'path' ], $query );
		rawurlencode( base64_encode( hash_hmac( 'sha256', $message, $secret_key, TRUE ) ) );

		//'url' => $this->getGlobal( "FpsURL" ),
		$url .= "?{$query_str}&Signature={$signature}";

		self::doRequest( $url );

		//	'AWSAccessKeyId' => $this->account_config[ 'AccessKey' ],
		//	'UrlEndPoint' => $this->getGlobal( "ReturnURL" ),
	}
}
