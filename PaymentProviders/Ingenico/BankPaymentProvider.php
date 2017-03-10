<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Context;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Handle bank payments via Ingenico
 * Will eventually implement PaymentProvider, but right now just looks
 * up iDEAL banks. Caches the results in the PSR-6 cache defined at
 * config key 'cache'.
 */
class BankPaymentProvider extends IngenicoPaymentProvider {

	/**
	 * @var array()
	 */
	protected $cacheParameters;

	/**
	 * @var CacheItemPoolInterface
	 */
	protected $cache;

	public function __construct( array $options = array() ) {
		parent::__construct( $options );
		$this->cacheParameters = $options['cache-parameters'];
		// FIXME: provide objects in constructor
		$config = Context::get()->getConfiguration();
		$this->cache = $config->object( 'cache' );
	}

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
		$cacheItem = $this->cache->getItem( $cacheKey );

		if ( !$cacheItem->isHit() || $this->shouldBeExpired( $cacheItem ) ) {
			$query = array(
				'countryCode' => $country,
				'currencyCode' => $currency
			);
			$path = "products/$productId/directory";
			$response = $this->api->makeApiCall( $path, 'GET', $query );

			$banks = array();

			foreach ( $response['entries'] as $entry ) {
				$banks[$entry['issuerId']] = $entry['issuerName'];
			}
			$duration = $this->cacheParameters['duration'];
			$cacheItem->set( array(
				'value' => $banks,
				'expiration' => time() + $duration
			) );
			$cacheItem->expiresAfter( $duration );
			$this->cache->save( $cacheItem );
		}
		$cached = $cacheItem->get();
		return $cached['value'];
	}

	protected function makeCacheKey( $country, $currency, $productId ) {
		$base = $this->cacheParameters['key-base'];
		return "{$base}_{$country}_{$currency}_{$productId}";
	}

	/**
	 * Lame workaround to mysterious Memcache non-expiry bug. Memcache
	 * seems to hold things for too long in certain circumstances.
	 * TODO: move to Core if we need to use this elsewhere. Though another
	 * layer of cache wrapping seems unfun.
	 *
	 * @param \Psr\Cache\CacheItemInterface $cacheItem
	 * @return bool True if the item should have been dropped by Memcache
	 */
	protected function shouldBeExpired( $cacheItem ) {
		$value = $cacheItem->get();
		if ( !isset( $value['expiration'] ) ) {
			return true;
		}
		$expiration = $value['expiration'];
		return $expiration < time();
	}
}
