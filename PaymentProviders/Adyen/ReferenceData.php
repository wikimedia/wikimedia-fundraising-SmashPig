<?php namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;

class ReferenceData {

	static $methods = array(
		'alipay' => array(
			'method' => 'ew',
			'submethod' => 'ew_alipay',
		),
		'amex' => array(
			'method' => 'cc',
			'submethod' => 'amex',
		),
		// International Bank Transfer (IBAN)
		'banktransfer_IBAN' => array(
			'method' => 'bt',
			'submethod' => 'iban',
		),
		// China Union Pay
		'cup' => array(
			'method' => 'cc',
			'submethod' => 'cup',
		),
		'diners' => array(
			'method' => 'cc',
			'submethod' => 'dc',
		),
		// Sofortüberweisung
		'directEbanking' => array(
			'method' => 'rtbt',
			'submethod' => 'rtbt_sofortuberweisung',
		),
		'discover' => array(
			'method' => 'cc',
			'submethod' => 'discover',
		),
		'dotpay' => array(
			'method' => 'ew',
			'submethod' => 'ew_dotpay',
		),
		'ideal' => array(
			'method' => 'rtbt',
			'submethod' => 'rtbt_ideal',
		),
		'mc' => array(
			'method' => 'cc',
			'submethod' => 'mc',
			'variants' => array(
				'mcdebit' => 'mc-debit',
			),
		),
		'multibanco' => array(
			'method' => 'rtbt',
			'submethod' => 'rtbt_multibanco',
		),
		'safetypay' => array(
			'method' => 'rtbt',
			'submethod' => 'rtbt_safetypay',
		),
		'sepadirectdebit' => array(
			'method' => 'dd',
			'submethod' => 'dd_sepa',
		),
		'tenpay' => array(
			'method' => 'ew',
			'submethod' => 'ew_tenpay',
		),
		'trustly' => array(
			'method' => 'obt',
			'submethod' => 'trustly',
		),
		'visa' => array(
			'method' => 'cc',
			'submethod' => 'visa',
			'variants' => array(
				'visabeneficial' => 'visa-beneficial', // guessing at Adyen code
				'visadebit' => 'visa-debit',
				'visaelectron' => 'visa-electron', // guessing at Adyen code
			)
		),
	);

	/**
	 * @param string $method Adyen's 'Payment Method'
	 * @param string $variant Adyen's 'Payment Method Variant'
	 * @returns array first entry is our payment_method, second is our payment_submethod
	 */
	public static function decodePaymentMethod( $method, $variant ) {
		if ( !array_key_exists( $method, self::$methods ) ) {
			throw new OutOfBoundsException( "Unknown Payment Method $method " );
		}
		$entry = self::$methods[$method];
		$ourMethod = $entry['method'];
		if ( $variant && array_key_exists( 'variants', $entry ) &&
			array_key_exists( $variant, $entry['variants'] ) ) {
			$ourSubmethod = $entry['variants'][$variant];
		} else {
			$ourSubmethod = $entry['submethod'];
		}
		return array( $ourMethod, $ourSubmethod );
	}
}
