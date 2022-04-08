<?php namespace SmashPig\PaymentProviders\PayPal;

use LogicException;
use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;

class PayPalPaymentsAPI {

	/**
	 * @param array $post_fields Associative array of fields posted to listener
	 * @return bool
	 */
	public function validate( $post_fields = [] ) {
		$post_fields['cmd'] = '_notify-validate';

		$url = Context::get()->getProviderConfiguration()->val( 'postback-url' );
		$request = new OutboundRequest( $url, 'POST' );
		$request->setBody( $post_fields );

		$response = $request->execute();

		if ( $response['body'] === 'VERIFIED' ) {
			return true;
		} else {
			return false;
		}
// elseif ( $response['body'] === 'INVALID' ) {
// return false;
// }

		throw new LogicException(
			'EnumValidator should not allow reaching this point!'
		);
	}

}
