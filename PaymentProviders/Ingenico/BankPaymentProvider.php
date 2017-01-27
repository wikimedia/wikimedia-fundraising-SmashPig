<?php

namespace SmashPig\PaymentProviders\Ingenico;

/**
 * Handle bank payments via Ingenico
 * Will eventually implement PaymentProvider, but right now just looks
 * up iDEAL banks
 */
class BankPaymentProvider extends IngenicoPaymentProvider {

	/**
	 * Look up banks
	 * @param string $country 2 letter country ISO code
	 * @param string $currency 3 letter currency ISO code
	 * @param int $productId Numeric Ingenico id of payment product we're
	 *  listing banks for. Defaults to the code for iDEAL, the only product
	 *  supported as of early 2017
	 * @return array Keys are bank codes, values are names
	 */
	public function getBankList( $country, $currency, $productId = 809 ) {
		$query = array(
			'countryCode' => $country,
			'currencyCode' => $currency
		);
		$path = "products/$productId/directory";
		$response = $this->makeApiCall( $path, 'GET', $query );

		// TODO: base class should probably decode
		$decoded = json_decode( $response['body'] );

		$banks = array();

		foreach( $decoded->entries as $entry ) {
			$banks[$entry->issuerId] = $entry->issuerName;
		}
		return $banks;
	}
}
