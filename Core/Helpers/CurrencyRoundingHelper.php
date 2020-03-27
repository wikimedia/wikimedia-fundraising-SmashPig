<?php


namespace SmashPig\Core\Helpers;

class CurrencyRoundingHelper {

	/**
	 * These currencies cannot have cents.
	 *
	 * @var array
	 */
	public static $non_fractional_currencies = [
		'CLP',
		'DJF',
		'IDR',
		'JPY',
		'KMF',
		'KRW',
		'MGA',
		'PYG',
		'VND',
		'XAF',
		'XOF',
		'XPF',
	];

	/**
	 * Currencies whose minor unit is exponent 3 (three decimal places)
	 *
	 * @var array
	 */
	public static $exponent3_currencies = [
		'BHD',
		'CLF',
		'IQD',
		'KWD',
		'LYD',
		'MGA',
		'MRO',
		'OMR',
		'TND',
	];

	/**
	 * Some currencies, like JPY, don't exist in fractional amounts.
	 * This rounds an amount to the appropriate number of decimal places.
	 *
	 * @param float $amount
	 * @param string $currencyCode
	 *
	 * @return string rounded amount
	 */
	public static function round( float $amount, string $currencyCode ): string {
		$amount = floatval( $amount );
		if ( self::isFractionalCurrency( $currencyCode ) ) {
			$precision = 2;
			if ( self::isExponent3Currency( $currencyCode ) ) {
				$precision = 3;
			}
			return number_format( $amount, $precision, '.', '' );
		} else {
			return (string)floor( $amount );
		}
	}

	/**
	 * @param string $currency_code The three-character currency code.
	 *
	 * @return bool
	 */
	public static function isFractionalCurrency( string $currency_code ): bool {
		if ( in_array( strtoupper( $currency_code ),
			static::$non_fractional_currencies ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if ISO 4217 (https://www.iso.org/iso-4217-currency-codes.html)
	 * defines the currency's minor units as being expressed
	 * using exponent 3 (three decimal places).
	 *
	 * @param string $currency_code The three-character currency code.
	 *
	 * @return bool
	 */
	public static function isExponent3Currency( string $currency_code ): bool {
		if ( in_array( strtoupper( $currency_code ),
			static::$exponent3_currencies ) ) {
			return true;
		}
		return false;
	}

}
