<?php namespace SmashPig\PaymentProviders\AstroPay;

use OutOfBoundsException;

class ReferenceData {
	public static function decodePaymentSubmethod( $code ) {
		$submethods = array(
			'AG' => 'argen',
			'AE' => 'amex',
			'AU' => 'aura',
			'B' => 'bradesco',
			'BB' => 'banco_do_brasil',
			'BG' => 'cash_provencia_pagos',
			'BL' => 'cash_boleto',
			'BM' => 'cash_banamex',
			'BP' => 'bbva', // Peru, bank transfer
			'BV' => 'cash_bancomer', // Mexico, aka BBVA
			'BX' => 'banco_de_chile',
			'CA' => 'caixa',
			'CB' => 'baloto',
			'CL' => 'cabal',
			'CM' => 'cmr',
			'CR' => 'carulla',
			'CS' => 'cencosud',
			'DA' => 'cash_davivienda',
			'DC' => 'diners',
			'DD' => 'dineromail', // (Transfer)
			'DM' => 'cash_dineromail', // (Cash)
			'DS' => 'discover',
			'EF' => 'cash_pago_efectivo',
			'EL' => 'elo',
			'EQ' => 'quindio',
			'EX' => 'almancenes',
			'EY' => 'cash_efecty',
			'H' => 'hsbc',
			'HI' => 'hiper',
			'I' => 'itau',
			'IB' => 'interbank',
			'JC' => 'jcb',
			'MC' => 'mc',
			'MD' => 'mc-debit',
			'MG' => 'magna',
			'ML' => 'mercadolivre',
			'NJ' => 'naranja',
			'NT' => 'nativa',
			'OC' => 'banco_de_occidente',
			'OX' => 'cash_oxxo',
			'PA' => 'bcp', // Peru, "via LatinAmericanPayments"
			'PC' => 'pse', // Columbia, "all banks"
			'PF' => 'cash_pago_facil',
			'PR' => 'presto',
			'RE' => 'cash_red_pagos',
			'RL' => 'red_link',
			'RP' => 'cash_rapipago',
			'SB' => 'santander', // Brazil
			'SI' => 'santander_rio', // Argentina
			'SM' => 'cash_santander', // Mexico
			'SP' => 'servipag',
			'SX' => 'surtimax',
			'TS' => 'shopping',
			'VD' => 'visa-debit',
			'VI' => 'visa',
			'WP' => 'webpay',
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
