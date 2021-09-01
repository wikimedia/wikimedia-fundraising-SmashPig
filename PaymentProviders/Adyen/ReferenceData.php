<?php namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;

class ReferenceData {

	protected static $methods = [
		'alipay' => [
			'method' => 'ew',
			'submethod' => 'ew_alipay',
		],
		'amex' => [
			'method' => 'cc',
			'submethod' => 'amex',
		],
		'applepay' => [
			'method' => 'apple',
			'submethod' => 'apple'
		],
		'bijcard' => [
			'method' => 'cc',
			'submethod' => 'bij',
		],
		// International Bank Transfer (IBAN)
		'banktransfer_IBAN' => [
			'method' => 'bt',
			'submethod' => 'iban',
		],
		'cartebancaire' => [
			'method' => 'cc',
			'submethod' => 'cb',
		],
		// China Union Pay
		'cup' => [
			'method' => 'cc',
			'submethod' => 'cup',
		],
		'diners' => [
			'method' => 'cc',
			'submethod' => 'dc',
		],
		// Sofortüberweisung
		'directEbanking' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_sofortuberweisung',
		],
		'discover' => [
			'method' => 'cc',
			'submethod' => 'discover',
		],
		'dotpay' => [
			'method' => 'ew',
			'submethod' => 'ew_dotpay',
		],
		'ideal' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_ideal',
		],
		'jcb' => [
			'method' => 'cc',
			'submethod' => 'jcb',
		],
		'mc' => [
			'method' => 'cc',
			'submethod' => 'mc',
			'variants' => [
				'mcdebit' => 'mc-debit',
			],
		],
		'maestro' => [
			'method' => 'cc',
			'submethod' => 'maestro',
		],
		'multibanco' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_multibanco',
		],
		'safetypay' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_safetypay',
		],
		'sepadirectdebit' => [
			'method' => 'dd',
			'submethod' => 'dd_sepa',
		],
		'tenpay' => [
			'method' => 'ew',
			'submethod' => 'ew_tenpay',
		],
		'trustly' => [
			'method' => 'obt',
			'submethod' => 'trustly',
		],
		'visa' => [
			'method' => 'cc',
			'submethod' => 'visa',
			'variants' => [
				'visabeneficial' => 'visa-beneficial', // guessing at Adyen code
				'visadebit' => 'visa-debit',
				'visaelectron' => 'visa-electron', // guessing at Adyen code
			]
		],
		// Debit card issued by Visa Europe
		'vpay' => [
			'method' => 'cc',
			'submethod' => 'visa-debit',
		],
		'visadankort' => [
			'method' => 'cc',
			'submethod' => 'visa',
		]
	];

	/**
	 * @param string $method Adyen's 'Payment Method'
	 * @param string $variant Adyen's 'Payment Method Variant'
	 * @return array first entry is our payment_method, second is our payment_submethod
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
		return [ $ourMethod, $ourSubmethod ];
	}
}
