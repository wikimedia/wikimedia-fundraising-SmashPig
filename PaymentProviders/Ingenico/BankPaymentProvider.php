<?php

namespace SmashPig\PaymentProviders\Ingenico;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use SmashPig\Core\Context;
use Symfony\Component\HttpFoundation\Response;
use Smashpig\Core\ApiException;

/**
 * Handle bank payments via Ingenico
 * Will eventually implement PaymentProvider, but right now just looks
 * up iDEAL banks. Caches the results in the PSR-6 cache defined at
 * config key 'cache'.
 */
class BankPaymentProvider extends PaymentProvider {

	/**
	 * @var array()
	 */
	protected $cacheParameters;

	/**
	 * @var CacheItemPoolInterface
	 */
	protected $cache;

	public function __construct( array $options = [] ) {
		parent::__construct( $options );
		$this->cacheParameters = $options['cache-parameters'];
		// FIXME: provide objects in constructor
		$config = Context::get()->getGlobalConfiguration();
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
	 * @throws ApiException
	 * @throws \Psr\Cache\InvalidArgumentException
	 */
	public function getBankList( string $country, string $currency, int $productId = 809 ): array {
		$cacheKey = $this->makeCacheKey( $country, $currency, $productId );
		$cacheItem = $this->cache->getItem( $cacheKey );

		if ( !$cacheItem->isHit() || $this->shouldBeExpired( $cacheItem ) ) {
			$query = [
				'countryCode' => $country,
				'currencyCode' => $currency
			];
			$path = "products/$productId/directory";
			$banks = [];

			$response = $this->api->makeApiCall( $path, 'GET', $query );
			$this->checkForErrors( $response );

			if ( !empty( $response['entries'] ) ) {
				foreach ( $response['entries'] as $entry ) {
					$banks[$entry['issuerId']] = $entry['issuerName'];
				}
			}
			$duration = $this->cacheParameters['duration'];
			$cacheItem->set( [
				'value' => $banks,
				'expiration' => time() + $duration
			] );
			$cacheItem->expiresAfter( $duration );
			$this->cache->save( $cacheItem );
		}
		$cached = $cacheItem->get();
		return $cached['value'];
	}

	protected function makeCacheKey( string $country, string $currency, int $productId ): string {
		$base = $this->cacheParameters['key-base'];
		return "{$base}_{$country}_{$currency}_{$productId}";
	}

	/**
	 * Lame workaround to mysterious Memcache non-expiry bug. Memcache
	 * seems to hold things for too long in certain circumstances.
	 * TODO: move to Core if we need to use this elsewhere. Though another
	 * layer of cache wrapping seems unfun.
	 *
	 * @param CacheItemInterface $cacheItem
	 * @return bool True if the item should have been dropped by Memcache
	 */
	protected function shouldBeExpired( CacheItemInterface $cacheItem ): bool {
		$value = $cacheItem->get();
		if ( !isset( $value['expiration'] ) ) {
			return true;
		}
		$expiration = $value['expiration'];
		return $expiration < time();
	}

	protected function checkForErrors( array $response ) {
		if ( empty( $response['errors'] ) ) {
			return;
		}
		$errors = $response['errors'];
		if ( count( $errors ) === 1 && $errors[0]['httpStatusCode'] === Response::HTTP_NOT_FOUND ) {
			// If there is a single 404 error, that means there is no directory info for the
			// country/currency/product. That's legitimate, so return and cache the empty array
			return;
		}
		$stringified = json_encode( $errors );
		$apiException = new ApiException(
			"Ingenico Error {$response['error_id']}: $stringified"
		);
		$apiException->setRawErrors( $errors );
		throw $apiException;
	}
}
