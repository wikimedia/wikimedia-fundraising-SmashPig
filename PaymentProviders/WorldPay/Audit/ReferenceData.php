<?php
namespace Smashpig\PaymentProviders\WorldPay\Audit;

class ReferenceData {
	static function decode_card_type( $code ) {
		$mapping = array(
			'visa' => array(
				'BC000',
				'XV000',
				'BCVIY',
				'DE000',
				'XD000',
				'XVVIY',
				'PE000',
				'XE000',
				'VP001',
				'VPVIB',
				'VPVID',
				'VPVIR',
				'VPVIS',
				'VPVIX',
				'XP001',
				'XPVIB',
				'XPVID',
				'XPVIR',
				'XPVIS',
				'XPVIX',
			),

			'mc' => array(
				'AC000',
				'ACMCW',
				'ACMNW',
				'XA000',
				'XAMCW',
				'XAMNW',
				'ACMCY',
				'DM000',
				'XAMCY',
				'XN000',
				'VP002',
				'VPMCB',
				'VPMCO',
				'VPMCP',
				'VPMCF',
				'VPMCX',
				'XP002',
				'XPMCB',
				'XPMCO',
				'XPMCP',
				'XPMCF',
				'XPMCX',
			),

			'maestro' => array(
				'PM000',
				'XM000',
				'PMDOM',
				'XSDOM',
			),

			'jcb' => array(
				'KF000',
			),

			'laser' => array(
				'PT000',
			),

			'other' => array(
				'AS000',
				'AX000',
				'BP000',
				'CM000',
				'CO000',
				'CY000',
				'DC000',
				'DL000',
				'EF000',
				'FS000',
				'GE000',
				'JC000',
				'LC000',
				'LE000',
				'LY000',
				'OD000',
				'PL000',
				'SB000',
				'SC000',
				'SE000',
				'SG000',
				'SH000',
				'SP000',
				'SY000',
				'TE000',
				'VC000',
				'VE000',
			),
		);

		foreach ( $mapping as $type_name => $codes ) {
			if ( in_array( $code, $codes ) ) {
				return $type_name;
			}
		}

		throw new \Exception( "Unknown card type code: {$code}" );
	}
}
