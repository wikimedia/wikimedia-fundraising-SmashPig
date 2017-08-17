<?php

namespace SmashPig\PaymentProviders\Ingenico;

use OutOfBoundsException;

class ReferenceData {
	// FIXME: replace this whole class with payment_(sub)method.yaml files

	protected static $methods = array(
		'1' => array( 'payment_method' => 'cc', 'payment_submethod' => 'visa' ),
		'2' => array( 'payment_method' => 'cc', 'payment_submethod' => 'amex' ),
		'3' => array( 'payment_method' => 'cc', 'payment_submethod' => 'mc' ),
		'11' => array( 'payment_method' => 'bt', 'payment_submethod' => 'bt' ),
		'117' => array( 'payment_method' => 'cc', 'payment_submethod' => 'maestro' ),
		'118' => array( 'payment_method' => 'cc', 'payment_submethod' => 'solo' ),
		'124' => array( 'payment_method' => 'cc', 'payment_submethod' => 'laser' ),
		'125' => array( 'payment_method' => 'cc', 'payment_submethod' => 'jcb' ),
		'128' => array( 'payment_method' => 'cc', 'payment_submethod' => 'discover' ),
		'130' => array( 'payment_method' => 'cc', 'payment_submethod' => 'cb' ),
		'500' => array( 'payment_method' => 'obt', 'payment_submethod' => 'bpay' ),
		'701' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_nl' ),
		'702' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_de' ),
		'703' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_at' ),
		'704' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_fr' ),
		'705' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_gb' ),
		'706' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_be' ),
		'707' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_ch' ),
		'708' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_it' ),
		'709' => array( 'payment_method' => 'dd', 'payment_submethod' => 'dd_es' ),
		'805' => array( 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_nordea_sweden' ),
		'809' => array( 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_ideal' ),
		'810' => array( 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_enets' ),
		'836' => array( 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_sofortuberweisung' ),
		'840' => array( 'payment_method' => 'ew', 'payment_submethod' => 'ew_paypal' ),
		'841' => array( 'payment_method' => 'ew', 'payment_submethod' => 'ew_webmoney' ),
		'843' => array( 'payment_method' => 'ew', 'payment_submethod' => 'ew_moneybookers' ),
		'845' => array( 'payment_method' => 'ew', 'payment_submethod' => 'ew_cashu' ),
		'849' => array( 'payment_method' => 'ew', 'payment_submethod' => 'ew_yandex' ),
		'856' => array( 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_eps' ),
		'861' => array( 'payment_method' => 'ew', 'payment_submethod' => 'ew_alipay' ),
		'1503' => array( 'payment_method' => 'cash', 'payment_submethod' => 'cash_boleto' ),
	);

	/**
	 * Gets our normalized payment_method and payment_submethod params from the
	 * codes that GC uses
	 *
	 * @param string $paymentProductId
	 * @return array containing payment_method and payment_submethod
	 */
	public static function decodePaymentMethod( $paymentProductId ) {
		if ( !array_key_exists( $paymentProductId, self::$methods ) ) {
			throw new OutOfBoundsException( "Unknown Payment Product ID $paymentProductId " );
		}
		return self::$methods[$paymentProductId];
	}
}
