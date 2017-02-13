<?php

namespace SmashPig\PaymentProviders\Ingenico;

/**
 * Handle bank payments via Ingenico
 * Will eventually implement PaymentProvider, but right now just looks
 * up iDEAL banks. Caches the results in the PSR-6 cache defined at
 * config key 'cache'.
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
		$cacheKey = $this->makeCacheKey( $country, $currency, $productId );
		$cache = $this->config->object( 'cache' );
		$cacheItem = $cache->getItem( $cacheKey );

		if ( !$cacheItem->isHit() ) {
			$query = array(
				'countryCode' => $country,
				'currencyCode' => $currency
			);
			$path = "products/$productId/directory";
			$response = $this->makeApiCall( $path, 'GET', $query );

			// TODO: base class should probably decode
			$decoded = json_decode( $response['body'] );

			$banks = array();

			foreach ( $decoded->entries as $entry ) {
				$banks[$entry->issuerId] = $entry->issuerName;
			}
			$cacheItem->set( $banks );
			$duration = $this->config->val( 'bank-cache/duration' );
			$cacheItem->expiresAfter( $duration );
			$cache->save( $cacheItem );
		}
		return $cacheItem->get();
	}

	protected function makeCacheKey( $country, $currency, $productId ) {
		$base = $this->config->val( 'bank-cache/key' );
		return "{$base}_{$country}_{$currency}_{$productId}";
	}
}
