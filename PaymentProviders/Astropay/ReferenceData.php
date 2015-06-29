<?php namespace SmashPig\PaymentProviders\Astropay;

use OutOfBoundsException;

class ReferenceData {
	public static function decodePaymentSubmethod( $code ) {
		$submethods = array(
			'AG' => 'argen',
			'AE' => 'amex',
			'B' => 'bradesco',
			'BB' => 'banco_de_brasil',
			'BL' => 'boleto',
			'BM' => 'banamex',
			'BP' => 'bbva',
			'BV' => 'bbva', // Mexico, aka Bancomer
			'BX' => 'banco_de_chile',
			'CA' => 'caixa',
			'CL' => 'cabal',
			'CS' => 'cencosud',
			'DA' => 'davivienda',
			'DC' => 'dc',
			'DM' => 'dineromail',
			'EF' => 'pago_efectivo',
			'EL' => 'elo',
			'EY' => 'efecty',
			'H' => 'hsbc',
			'HI' => 'hiper',
			'I' => 'itau',
			'IB' => 'interbank',
			'MC' => 'mc',
			'MD' => 'mc_debit',
			'ML' => 'mercadolivre',
			'NJ' => 'naranja',
			'NT' => 'nativa',
			'OX' => 'oxxo',
			'PA' => 'bcp', // Peru, "via LatinAmericanPayments"
			'PC' => 'pse', // Columbia, "all banks"
			'PF' => 'pago_facil',
			'RP' => 'rapipago',
			'SB' => 'santander', // Brazil
			'SI' => 'santander', // Argentina
			'SM' => 'santander', // Mexico
			'TS' => 'tarjeta_shopping',
			'VD' => 'visa_debit',
			'VI' => 'visa',
			'WP' => 'other', // Chilean catch-all
		);

		if ( array_key_exists( $code, $submethods ) ) {
			return $submethods[$code];
		}

		throw new OutOfBoundsException( "Unknown payment submethod code: {$code}" );
	}

	public static function decodePaymentMethod( $name ) {
		$methods = array(
			'Bank Transfer' => 'bt',
			'Cash Payment' => 'cash',
			'Credit Card' => 'cc',
			'Debit Card' => 'cc',
		);

		if ( array_key_exists( $name, $methods ) ) {
			return $methods[$name];
		}

		throw new OutOfBoundsException( "Unknown payment method name: {$name}" );
	}
}
