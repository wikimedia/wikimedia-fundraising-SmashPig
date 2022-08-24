<?php

namespace SmashPig\PaymentData\ReferenceData;

class NationalCurrencies {
	/**
	 * Originally retrieved from reference on Jul 6, 2012
	 * http://fasteri.com/list/country-code-dialing-code-currency-timezone-of-countries.html
	 * Updated and corrected from various sources on Aug 11, 2022
	 * @param string $country Two letter ISO country code
	 * @return string|null
	 */
	public static function getNationalCurrency( string $country ) {
		$country = substr( $country, 0, 2 );
		$country = strtoupper( $country );
		$nationalCurrencies = self::getNationalCurrencies();
		if ( isset( $nationalCurrencies[$country] ) ) {
			return $nationalCurrencies[$country];
		} else {
			return null;
		}
	}

	public static function getNationalCurrencies() {
		$nationalCurrencies = [
			'AD' => 'EUR',
			'AE' => 'AED',
			'AF' => 'AFN',
			'AG' => 'XCD',
			'AI' => 'XCD',
			'AL' => 'ALL',
			'AM' => 'AMD',
			'AO' => 'AOA',
			'AR' => 'ARS',
			'AS' => 'USD',
			'AT' => 'EUR',
			'AU' => 'AUD',
			'AW' => 'AWG',
			'AX' => 'EUR',
			'AZ' => 'AZN',
			'BA' => 'BAM',
			'BB' => 'BBD',
			'BD' => 'BDT',
			'BE' => 'EUR',
			'BF' => 'XOF',
			'BG' => 'BGN',
			'BH' => 'BHD',
			'BI' => 'BIF',
			'BJ' => 'XOF',
			'BL' => 'EUR',
			'BM' => 'BMD',
			'BN' => 'BND',
			'BO' => 'BOB',
			'BQ' => 'USD',
			'BR' => 'BRL',
			'BS' => 'BSD',
			'BT' => 'BTN',
			'BW' => 'BWP',
			'BY' => 'BYN',
			'BZ' => 'BZD',
			'CA' => 'CAD',
			'CC' => 'AUD',
			'CD' => 'CDF',
			'CF' => 'XAF',
			'CG' => 'XAF',
			'CH' => 'CHF',
			'CI' => 'XOF',
			'CK' => 'NZD',
			'CL' => 'CLP',
			'CM' => 'XAF',
			'CN' => 'CNY',
			'CO' => 'COP',
			'CR' => 'CRC',
			'CU' => 'CUC',
			'CV' => 'CVE',
			'CW' => 'ANG',
			'CX' => 'AUD',
			'CY' => 'EUR',
			'CZ' => 'CZK',
			'DE' => 'EUR',
			'DJ' => 'DJF',
			'DK' => 'DKK',
			'DM' => 'XCD',
			'DO' => 'DOP',
			'DZ' => 'DZD',
			'EC' => 'USD',
			'EE' => 'EUR',
			'EG' => 'EGP',
			'EH' => 'MAD',
			'ER' => 'ERN',
			'ES' => 'EUR',
			'ET' => 'ETB',
			'FI' => 'EUR',
			'FJ' => 'FJD',
			'FK' => 'FKP',
			'FM' => 'USD',
			'FO' => 'DKK',
			'FR' => 'EUR',
			'GA' => 'XAF',
			'GB' => 'GBP',
			'GD' => 'XCD',
			'GE' => 'GEL',
			'GF' => 'EUR',
			'GG' => 'GBP',
			'GH' => 'GHS',
			'GI' => 'GIP',
			'GL' => 'DKK',
			'GM' => 'GMD',
			'GN' => 'GNF',
			'GP' => 'EUR',
			'GQ' => 'XAF',
			'GR' => 'EUR',
			'GT' => 'GTQ',
			'GU' => 'USD',
			'GW' => 'XOF',
			'GY' => 'GYD',
			'HK' => 'HKD',
			'HN' => 'HNL',
			'HR' => 'HRK',
			'HT' => 'HTG',
			'HU' => 'HUF',
			'ID' => 'IDR',
			'IE' => 'EUR',
			'IL' => 'ILS',
			'IM' => 'GBP',
			'IN' => 'INR',
			'IO' => 'USD',
			'IQ' => 'IQD',
			'IR' => 'IRR',
			'IS' => 'ISK',
			'IT' => 'EUR',
			'JE' => 'GBP',
			'JM' => 'JMD',
			'JO' => 'JOD',
			'JP' => 'JPY',
			'KE' => 'KES',
			'KG' => 'KGS',
			'KH' => 'KHR',
			'KI' => 'AUD',
			'KM' => 'KMF',
			'KP' => 'KPW',
			'KR' => 'KRW',
			'KV' => 'EUR',
			'KW' => 'KWD',
			'KY' => 'KYD',
			'KZ' => 'KZT',
			'LA' => 'LAK',
			'LB' => 'LBP',
			'LC' => 'XCD',
			'LI' => 'CHF',
			'LK' => 'LKR',
			'LR' => 'LRD',
			'LS' => 'LSL',
			'LT' => 'EUR',
			'LU' => 'EUR',
			'LV' => 'EUR',
			'LY' => 'LYD',
			'MA' => 'MAD',
			'MC' => 'EUR',
			'MD' => 'MDL',
			'ME' => 'EUR',
			'MG' => 'MGA',
			'MH' => 'USD',
			'MK' => 'MKD',
			'ML' => 'XOF',
			'MM' => 'MMK',
			'MN' => 'MNT',
			'MO' => 'MOP',
			'MP' => 'USD',
			'MQ' => 'EUR',
			'MR' => 'MRO',
			'MS' => 'XCD',
			'MT' => 'EUR',
			'MU' => 'MUR',
			'MV' => 'MVR',
			'MW' => 'MWK',
			'MX' => 'MXN',
			'MY' => 'MYR',
			'MZ' => 'MZN',
			'NA' => 'NAD',
			'NC' => 'XPF',
			'NE' => 'XOF',
			'NF' => 'AUD',
			'NG' => 'NGN',
			'NI' => 'NIO',
			'NL' => 'EUR',
			'NO' => 'NOK',
			'NP' => 'NPR',
			'NR' => 'AUD',
			'NU' => 'NZD',
			'NZ' => 'NZD',
			'OM' => 'OMR',
			'PA' => 'PAB',
			'PE' => 'PEN',
			'PF' => 'XPF',
			'PG' => 'PGK',
			'PH' => 'PHP',
			'PK' => 'PKR',
			'PL' => 'PLN',
			'PN' => 'NZD',
			'PR' => 'USD',
			'PS' => 'ILS',
			'PT' => 'EUR',
			'PW' => 'USD',
			'PY' => 'PYG',
			'QA' => 'QAR',
			'RE' => 'EUR',
			'RO' => 'RON',
			'RS' => 'RSD',
			'RU' => 'RUB',
			'RW' => 'RWF',
			'SA' => 'SAR',
			'SB' => 'SBD',
			'SC' => 'SCR',
			'SD' => 'SDG',
			'SE' => 'SEK',
			'SG' => 'SGD',
			'SH' => 'SHP',
			'SI' => 'EUR',
			'SK' => 'EUR',
			'SL' => 'SLL',
			'SM' => 'EUR',
			'SN' => 'XOF',
			'SO' => 'SOS',
			'SR' => 'SRD',
			'ST' => 'STD',
			'SV' => 'USD',
			'SX' => 'ANG',
			'SY' => 'SYP',
			'SZ' => 'SZL',
			'TC' => 'USD',
			'TD' => 'XAF',
			'TF' => 'EUR',
			'TG' => 'XOF',
			'TH' => 'THB',
			'TJ' => 'TJS',
			'TL' => 'USD',
			'TM' => 'TMT',
			'TN' => 'TND',
			'TO' => 'TOP',
			'TR' => 'TRY',
			'TT' => 'TTD',
			'TV' => 'AUD',
			'TW' => 'TWD',
			'TZ' => 'TZS',
			'UA' => 'UAH',
			'UG' => 'UGX',
			'UM' => 'USD',
			'US' => 'USD',
			'UY' => 'UYU',
			'UZ' => 'UZS',
			'VA' => 'EUR',
			'VC' => 'XCD',
			'VE' => 'VED',
			'VG' => 'USD',
			'VI' => 'USD',
			'VN' => 'VND',
			'VU' => 'VUV',
			'WF' => 'XPF',
			'WS' => 'WST',
			'YE' => 'YER',
			'YT' => 'EUR',
			'ZA' => 'ZAR',
			'ZM' => 'ZMK',
			'ZW' => 'ZWL'
		];
		return $nationalCurrencies;
	}
}
