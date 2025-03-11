<?php
namespace SmashPig\PaymentProviders\Gravy;

/**
 * These codes are listed per country here
 * https://docs.dlocal.com/docs/payment-method
 */
class ReferenceData {

	const EW_PAYMENT_METHOD = 'ew';
	const BT_PAYMENT_METHOD = 'bt';
	const DD_PAYMENT_METHOD = 'dd';
	const CC_PAYMENT_METHOD = 'cc';
	const APPLE_PAYMENT_METHOD = 'apple';
	const GOOGLE_PAYMENT_METHOD = 'google';
	const PAYPAL_PAYMENT_METHOD = 'paypal';
	const RTBT_PAYMENT_METHOD = 'rtbt';
	const VENMO_PAYMENT_METHOD = 'venmo';
	const STRIPE_PAYMENT_METHOD = 'stripe';
	const CASH_PAYMENT_METHOD = 'cash';

	protected static $methods = [
		'afterpay' => '',
		'alipay' => self::EW_PAYMENT_METHOD,
		'alipayhk' => self::EW_PAYMENT_METHOD,
		'amex' => self::CC_PAYMENT_METHOD,
		'applepay' => self::APPLE_PAYMENT_METHOD,
		'bacs' => self::DD_PAYMENT_METHOD,
		'bancontact' => self::CC_PAYMENT_METHOD,
		'banked' => self::BT_PAYMENT_METHOD,
		'bcp' => self::BT_PAYMENT_METHOD,
		'becs' => self::DD_PAYMENT_METHOD,
		'bitpay' => '', // Crypto payment service
		'boleto' => self::CASH_PAYMENT_METHOD,
		'boost' => '',
		'card' => self::CC_PAYMENT_METHOD,
		'carte-bancaire' => self::CC_PAYMENT_METHOD,
		'cashapp' => '',
		'chaseorbital' => '',
		'checkout-session' => '',
		'cirrus' => self::CC_PAYMENT_METHOD,
		'clearpay' => '',
		'click-to-pay' => '',
		'culiance' => self::CC_PAYMENT_METHOD,
		'dana' => '',
		'dankort' => self::CC_PAYMENT_METHOD,
		'dcb' => '',
		'diners-club' => self::CC_PAYMENT_METHOD,
		'discover' => self::CC_PAYMENT_METHOD,
		'dlocal' => '',
		'ebanx' => '',
		'eftpos-australia' => self::CC_PAYMENT_METHOD,
		'elo' => self::CC_PAYMENT_METHOD,
		'eps' => self::RTBT_PAYMENT_METHOD,
		'everydaypay' => '',
		'gcash' => '',
		'giropay' => '',
		'givingblock' => '',
		'gocardless' => '',
		'googlepay' => self::GOOGLE_PAYMENT_METHOD,
		'gopay' => '',
		'grabpay' => '',
		'hipercard' => self::CC_PAYMENT_METHOD,
		'ideal' => self::RTBT_PAYMENT_METHOD,
		'jcb' => self::CC_PAYMENT_METHOD,
		'kakaopay' => '',
		'klarna' => '',
		'laybuy' => '',
		'linepay' => '',
		'linkaja' => '',
		'maestro' => self::CC_PAYMENT_METHOD,
		'mastercard' => self::CC_PAYMENT_METHOD,
		'maybankqrpay' => '',
		'mir' => self::CC_PAYMENT_METHOD,
		'multibanco' => self::RTBT_PAYMENT_METHOD,
		'multipago' => '',
		'netbanking' => self::BT_PAYMENT_METHOD,
		'network-token' => '',
		'nyce' => self::CC_PAYMENT_METHOD,
		'oney_10x' => '',
		'oney_12x' => '',
		'oney_3x' => '',
		'oney_4x' => '',
		'oney_6x' => '',
		'other' => self::CC_PAYMENT_METHOD,
		'ovo' => '',
		'oxxo' => self::CASH_PAYMENT_METHOD,
		'payid' => '',
		'paymaya' => '',
		'paypal' => self::PAYPAL_PAYMENT_METHOD,
		'paypalpaylater' => self::PAYPAL_PAYMENT_METHOD,
		'payto' => '',
		'pix' => self::CASH_PAYMENT_METHOD,
		'pse' => self::BT_PAYMENT_METHOD,
		'pulse' => self::CC_PAYMENT_METHOD,
		'rabbitlinepay' => '',
		'razorpay' => '',
		'rupay' => self::CC_PAYMENT_METHOD,
		'scalapay' => '',
		'sepa' => self::RTBT_PAYMENT_METHOD,
		'shopeepay' => '',
		'singteldash' => '',
		'smartpay' => '',
		'sofort' => self::RTBT_PAYMENT_METHOD,
		'star' => self::CC_PAYMENT_METHOD,
		'stripedd' => self::STRIPE_PAYMENT_METHOD,
		'thaiqr' => '',
		'touchngo' => '',
		'truemoney' => '',
		'trustly' => self::DD_PAYMENT_METHOD,
		'trustlyeurope' => self::DD_PAYMENT_METHOD,
		'trustlyus' => self::DD_PAYMENT_METHOD,
		'uatp' => self::CC_PAYMENT_METHOD,
		'unionpay' => self::CC_PAYMENT_METHOD,
		'venmo' => self::VENMO_PAYMENT_METHOD,
		'vipps' => '',
		'visa' => self::CC_PAYMENT_METHOD,
		'waave' => '',
		'wechat' => '',
		'zippay' => '',
	];

	protected static $cardPaymentSubmethods = [
		'amex' => 'amex',
		'bancontact' => '',
		'carte-bancaire' => 'cb',
		'cirrus' => '',
		'culiance' => '',
		'dankort' => '',
		'diners-club' => 'diners',
		'discover' => 'discover',
		'eftpos-australia' => '',
		'elo' => 'elo',
		'hipercard' => 'hipercard',
		'jcb' => 'jcb',
		'maestro' => 'maestro',
		'mastercard' => 'mc',
		'mir' => '',
		'nyce' => '',
		'other' => '',
		'pulse' => '',
		'rupay' => 'rupay',
		'star' => '',
		'uatp' => '',
		'unionpay' => '',
		'visa' => 'visa',
	];

	protected static $ewSubmethods = [
		'alipay' => 'ew_alipay',
		'alipayhk' => 'ew_alipay',
	];

	protected static $rtbtSubmethods = [
		'eps' => 'rtbt',
		'ideal' => 'rtbt',
		'multibanco' => 'rtbt',
		'sepa' => 'rtbt',
		'sofort' => 'rtbt',
	];

	protected static $ddSubmethods = [
		'trustly' => 'ach',
		'trustlyeurope' => '',
		'trustlyus' => 'ach',
	];

	protected static $btSubmethods = [
		'bcp' => 'bcp',
		'pse' => 'pse',
		'netbanking' => 'netbanking'
	];

	protected static $cashSubmethods = [
		'pix' => 'pix',
		'oxxo' => 'cash_oxxo',
	];

	public static function decodePaymentMethod( string $method, ?string $scheme = '' ): array {
		$methods = self::$methods;
		$payment_method = $methods[$method] ?? '';
		$payment_submethod = '';

		switch ( $payment_method ) {
			case self::EW_PAYMENT_METHOD:
				$payment_submethod = self::$ewSubmethods[$scheme];
				break;
			case self::RTBT_PAYMENT_METHOD:
				$payment_submethod = self::$rtbtSubmethods[$scheme];
				break;
			case self::CC_PAYMENT_METHOD:
			case self::APPLE_PAYMENT_METHOD:
			case self::GOOGLE_PAYMENT_METHOD:
				$payment_submethod = self::$cardPaymentSubmethods[$scheme];
				break;
			case self::DD_PAYMENT_METHOD:
				$payment_submethod = self::$ddSubmethods[$method];
				break;
			case self::BT_PAYMENT_METHOD:
				$payment_submethod = self::$btSubmethods[$method];
				break;
			case self::CASH_PAYMENT_METHOD:
				$payment_submethod = self::$cashSubmethods[$method];
				break;
			default:
				break;
		}

		return [ $payment_method, $payment_submethod ];
	}
}
