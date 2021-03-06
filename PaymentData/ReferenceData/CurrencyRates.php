<?php
/**
 * Automatically generated from make_exchange_refs.drush.inc:templates/ref_source.php.twig
 * -- do not edit! --
 * Instead, run drush make-exchange-refs /output/dir and look in the specified folder.
 */
namespace SmashPig\PaymentData\ReferenceData;

class CurrencyRates {
	/**
	 * Supplies rough (not up-to-date) conversion rates for currencies
	 */

	public static $lastUpdated = '2021-03-04';

	public static function getCurrencyRates() {
		// Not rounding numbers under 1 because I don't think that's a big issue and could cause issues with the max check.
		$currencyRates = [
			'ADF' => 5.45,
			'ADP' => 138,
			'AED' => 3.67,
			'AFA' => 77,
			'AFN' => 77,
			'ALL' => 102,
			'AMD' => 522,
			'ANG' => 1.79,
			'AOA' => 622,
			'AON' => 622,
			'ARS' => 90,
			'ATS' => 11,
			'AUD' => 1.29,
			'AWG' => 1.79,
			'AZM' => 8500,
			'AZN' => 1.7,
			'BAM' => 1.63,
			'BBD' => 2,
			'BDT' => 83,
			'BEF' => 34,
			'BGL' => 1.63,
			'BGN' => 1.63,
			'BHD' => 0.37503712235129,
			'BIF' => 1927,
			'BMD' => 1,
			'BND' => 1.33,
			'BOB' => 6.74,
			'BRL' => 5.62,
			'BSD' => 1,
			'BTN' => 73,
			'BWP' => 11,
			'BYR' => 26065,
			'BZD' => 1.97,
			'CAD' => 1.26,
			'CDF' => 1975,
			'CHF' => 0.92373627643191,
			'CLP' => 729,
			'CNY' => 6.47,
			'COP' => 3660,
			'CRC' => 601,
			'CUC' => 1,
			'CUP' => 25,
			'CVE' => 92,
			'CYP' => 0.4866220993031,
			'CZK' => 22,
			'DEM' => 1.63,
			'DJF' => 178,
			'DKK' => 6.18,
			'DOP' => 57,
			'DZD' => 133,
			'ECS' => 24094,
			'EEK' => 13,
			'EGP' => 16,
			'ESP' => 138,
			'ETB' => 40,
			'EUR' => 0.83144322027478,
			'FIM' => 4.94,
			'FJD' => 2.02,
			'FKP' => 0.71743442231745,
			'FRF' => 5.45,
			'GBP' => 0.71743442231745,
			'GEL' => 3.3,
			'GHC' => 57159,
			'GHS' => 5.72,
			'GIP' => 0.71743442231745,
			'GMD' => 51,
			'GNF' => 10041,
			'GRD' => 283,
			'GTQ' => 7.53,
			'GYD' => 201,
			'HKD' => 7.76,
			'HNL' => 24,
			'HRK' => 6.3,
			'HTG' => 76,
			'HUF' => 303,
			'IDR' => 14279,
			'IEP' => 0.65481474833248,
			'ILS' => 3.3,
			'INR' => 73,
			'IQD' => 1442,
			'IRR' => 42010,
			'ISK' => 127,
			'ITL' => 1610,
			'JMD' => 149,
			'JOD' => 0.70900000000001,
			'JPY' => 107,
			'KES' => 109,
			'KGS' => 85,
			'KHR' => 4019,
			'KMF' => 409,
			'KPW' => 135,
			'KRW' => 1126,
			'KWD' => 0.30191153907257,
			'KYD' => 0.83333299999999,
			'KZT' => 418,
			'LAK' => 9170,
			'LBP' => 1508,
			'LKR' => 194,
			'LRD' => 173,
			'LSL' => 15,
			'LTL' => 2.87,
			'LUF' => 34,
			'LVL' => 0.58434162098198,
			'LYD' => 4.42,
			'MAD' => 8.82,
			'MDL' => 17,
			'MGA' => 3716,
			'MGF' => 9150,
			'MKD' => 51,
			'MMK' => 1392,
			'MNT' => 2620,
			'MOP' => 7.99,
			'MRO' => 357,
			'MTL' => 0.35693857446396,
			'MUR' => 39,
			'MVR' => 15,
			'MWK' => 770,
			'MXN' => 21,
			'MYR' => 4.05,
			'MZM' => 73880,
			'MZN' => 74,
			'NAD' => 15,
			'NGN' => 380,
			'NIO' => 35,
			'NLG' => 1.83,
			'NOK' => 8.52,
			'NPR' => 115,
			'NZD' => 1.38,
			'OMR' => 0.38383957365098,
			'PAB' => 1,
			'PEN' => 3.63,
			'PGK' => 3.43,
			'PHP' => 49,
			'PKR' => 157,
			'PLN' => 3.78,
			'PTE' => 167,
			'PYG' => 6541,
			'QAR' => 3.61,
			'ROL' => 40517,
			'RON' => 4.05,
			'RSD' => 97,
			'RUB' => 74,
			'RWF' => 973,
			'SAR' => 3.75,
			'SBD' => 7.89,
			'SCR' => 20,
			'SDD' => 34467,
			'SDG' => 345,
			'SDP' => 2261,
			'SEK' => 8.45,
			'SGD' => 1.33,
			'SHP' => 0.71743442231745,
			'SIT' => 199,
			'SKK' => 25,
			'SLL' => 10105,
			'SOS' => 558,
			'SRD' => 14,
			'SRG' => 14018,
			'STD' => 20304,
			'SVC' => 8.75,
			'SYP' => 513,
			'SZL' => 15,
			'THB' => 30,
			'TJS' => 11,
			'TMM' => 16770,
			'TMT' => 3.35,
			'TND' => 2.69,
			'TOP' => 2.22,
			'TRL' => 7456951,
			'TRY' => 7.46,
			'TTD' => 6.66,
			'TWD' => 28,
			'TZS' => 2309,
			'UAH' => 28,
			'UGX' => 3641,
			'USD' => 1,
			'UYU' => 43,
			'UZS' => 10427,
			'VEB' => 1.86275657102E+14,
			'VEF' => 186275657102,
			'VND' => 22940,
			'VUV' => 112,
			'WST' => 2.43,
			'XAF' => 545,
			'XAG' => 0.038609238198237,
			'XAU' => 0.000584537038152,
			'XCD' => 2.7,
			'XEU' => 0.83144322027478,
			'XOF' => 545,
			'XPD' => 0.000423507866364,
			'XPF' => 99,
			'XPT' => 0.00085367167460603,
			'YER' => 250,
			'YUN' => 97,
			'ZAR' => 15,
			'ZMK' => 5176,
			'ZWD' => 373,
		];

		return $currencyRates;
	}
}
