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

	public static $lastUpdated = '2020-02-24';

	public static function getCurrencyRates() {
		// Not rounding numbers under 1 because I don't think that's a big issue and could cause issues with the max check.
		$currencyRates = [
			'ADF' => 6.05,
			'ADP' => 154,
			'AED' => 3.67,
			'AFA' => 77,
			'AFN' => 77,
			'ALL' => 111,
			'AMD' => 465,
			'ANG' => 1.79,
			'AOA' => 488,
			'AON' => 488,
			'ARS' => 62,
			'ATS' => 13,
			'AUD' => 1.51,
			'AWG' => 1.79,
			'AZM' => 8500,
			'AZN' => 1.7,
			'BAM' => 1.8,
			'BBD' => 2,
			'BDT' => 83,
			'BEF' => 37,
			'BGL' => 1.8,
			'BGN' => 1.8,
			'BHD' => 0.374810624197,
			'BIF' => 1860,
			'BMD' => 1,
			'BND' => 1.4,
			'BOB' => 6.72,
			'BRL' => 4.39,
			'BSD' => 1,
			'BTN' => 72,
			'BWP' => 11,
			'BYR' => 22145,
			'BZD' => 1.97,
			'CAD' => 1.33,
			'CDF' => 1676,
			'CHF' => 0.97929020547776,
			'CLP' => 806,
			'CNY' => 7.03,
			'COP' => 3402,
			'CRC' => 557,
			'CUC' => 1,
			'CUP' => 25,
			'CVE' => 102,
			'CYP' => 0.54010619446764,
			'CZK' => 23,
			'DEM' => 1.8,
			'DJF' => 178,
			'DKK' => 6.89,
			'DOP' => 53,
			'DZD' => 120,
			'ECS' => 24094,
			'EEK' => 14,
			'EGP' => 16,
			'ESP' => 154,
			'ETB' => 32,
			'EUR' => 0.92282622236359,
			'FIM' => 5.49,
			'FJD' => 2.2,
			'FKP' => 0.77355578576054,
			'FRF' => 6.05,
			'GBP' => 0.77355578576054,
			'GEL' => 2.79,
			'GHC' => 53054,
			'GHS' => 5.31,
			'GIP' => 0.77355578576054,
			'GMD' => 51,
			'GNF' => 9408,
			'GRD' => 314,
			'GTQ' => 7.45,
			'GYD' => 204,
			'HKD' => 7.79,
			'HNL' => 24,
			'HRK' => 6.87,
			'HTG' => 96,
			'HUF' => 312,
			'IDR' => 13905,
			'IEP' => 0.7267847109896,
			'ILS' => 3.42,
			'INR' => 72,
			'IQD' => 1184,
			'IRR' => 42052,
			'ISK' => 128,
			'ITL' => 1787,
			'JMD' => 138,
			'JOD' => 0.709,
			'JPY' => 111,
			'KES' => 100,
			'KGS' => 70,
			'KHR' => 4024,
			'KMF' => 454,
			'KPW' => 135,
			'KRW' => 1218,
			'KWD' => 0.30580451798473,
			'KYD' => 0.83333299999999,
			'KZT' => 374,
			'LAK' => 8721,
			'LBP' => 1508,
			'LKR' => 179,
			'LRD' => 197,
			'LSL' => 15,
			'LTL' => 3.19,
			'LUF' => 37,
			'LVL' => 0.64856596038206,
			'LYD' => 1.39,
			'MAD' => 9.61,
			'MDL' => 18,
			'MGA' => 3680,
			'MGF' => 9150,
			'MKD' => 56,
			'MMK' => 1429,
			'MNT' => 2620,
			'MOP' => 8.03,
			'MRO' => 369,
			'MTL' => 0.39616929726071,
			'MUR' => 36,
			'MVR' => 15,
			'MWK' => 723,
			'MXN' => 19,
			'MYR' => 4.22,
			'MZM' => 64246,
			'MZN' => 64,
			'NAD' => 15,
			'NGN' => 362,
			'NIO' => 33,
			'NLG' => 2.03,
			'NOK' => 9.35,
			'NPR' => 113,
			'NZD' => 1.58,
			'OMR' => 0.38399685625247,
			'PAB' => 1,
			'PEN' => 3.37,
			'PGK' => 3.33,
			'PHP' => 51,
			'PKR' => 154,
			'PLN' => 3.97,
			'PTE' => 185,
			'PYG' => 6413,
			'QAR' => 3.63,
			'ROL' => 44257,
			'RON' => 4.43,
			'RSD' => 108,
			'RUB' => 65,
			'RWF' => 932,
			'SAR' => 3.75,
			'SBD' => 8.09,
			'SCR' => 13,
			'SDD' => 5315,
			'SDG' => 53,
			'SDP' => 2261,
			'SEK' => 9.76,
			'SGD' => 1.4,
			'SHP' => 0.77355578576054,
			'SIT' => 221,
			'SKK' => 28,
			'SLL' => 9643,
			'SOS' => 556,
			'SRD' => 7.4,
			'SRG' => 7396,
			'STD' => 22580,
			'SVC' => 8.75,
			'SYP' => 215,
			'SZL' => 15,
			'THB' => 32,
			'TJS' => 9.63,
			'TMM' => 17287,
			'TMT' => 3.46,
			'TND' => 2.85,
			'TOP' => 2.25,
			'TRL' => 6123003,
			'TRY' => 6.12,
			'TTD' => 6.62,
			'TWD' => 30,
			'TZS' => 2298,
			'UAH' => 24,
			'UGX' => 3638,
			'USD' => 1,
			'UYU' => 38,
			'UZS' => 9472,
			'VEB' => 7349571135619,
			'VEF' => 7349571136,
			'VND' => 23185,
			'VUV' => 112,
			'WST' => 2.61,
			'XAF' => 605,
			'XAG' => 0.053383316740693,
			'XAU' => 0.00059898209336699,
			'XCD' => 2.7,
			'XEU' => 0.92282622236359,
			'XOF' => 605,
			'XPD' => 0.00037515815888001,
			'XPF' => 110,
			'XPT' => 0.001027857607858,
			'YER' => 250,
			'YUN' => 108,
			'ZAR' => 15,
			'ZMK' => 5176,
			'ZWD' => 373,
		];

		return $currencyRates;
	}
}
