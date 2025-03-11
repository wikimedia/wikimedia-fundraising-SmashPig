<?php
namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentData\PaymentMethod;

/**
 * These codes are listed per country here
 * https://docs.dlocal.com/docs/payment-method
 */
class ReferenceData {

	protected static $methods = [
		'afterpay' => '',
		'alipay' => PaymentMethod::EW,
		'alipayhk' => PaymentMethod::EW,
		'amex' => PaymentMethod::CC,
		'applepay' => PaymentMethod::APPLE,
		'bacs' => PaymentMethod::DD,
		'bancontact' => PaymentMethod::CC,
		'banked' => PaymentMethod::BT,
		'bcp' => PaymentMethod::BT,
		'becs' => PaymentMethod::DD,
		'bitpay' => '', // Crypto payment service
		'boleto' => PaymentMethod::CASH,
		'boost' => '',
		'card' => PaymentMethod::CC,
		'carte-bancaire' => PaymentMethod::CC,
		'cashapp' => '',
		'chaseorbital' => '',
		'checkout-session' => '',
		'cirrus' => PaymentMethod::CC,
		'clearpay' => '',
		'click-to-pay' => '',
		'culiance' => PaymentMethod::CC,
		'dana' => '',
		'dankort' => PaymentMethod::CC,
		'dcb' => '',
		'diners-club' => PaymentMethod::CC,
		'discover' => PaymentMethod::CC,
		'dlocal' => '',
		'ebanx' => '',
		'eftpos-australia' => PaymentMethod::CC,
		'elo' => PaymentMethod::CC,
		'eps' => PaymentMethod::RTBT,
		'everydaypay' => '',
		'gcash' => '',
		'giropay' => '',
		'givingblock' => '',
		'gocardless' => '',
		'googlepay' => PaymentMethod::GOOGLE,
		'gopay' => '',
		'grabpay' => '',
		'hipercard' => PaymentMethod::CC,
		'ideal' => PaymentMethod::RTBT,
		'jcb' => PaymentMethod::CC,
		'kakaopay' => '',
		'klarna' => '',
		'laybuy' => '',
		'linepay' => '',
		'linkaja' => '',
		'maestro' => PaymentMethod::CC,
		'mastercard' => PaymentMethod::CC,
		'maybankqrpay' => '',
		'mir' => PaymentMethod::CC,
		'multibanco' => PaymentMethod::RTBT,
		'multipago' => '',
		'netbanking' => PaymentMethod::BT,
		'network-token' => '',
		'nyce' => PaymentMethod::CC,
		'oney_10x' => '',
		'oney_12x' => '',
		'oney_3x' => '',
		'oney_4x' => '',
		'oney_6x' => '',
		'other' => PaymentMethod::CC,
		'ovo' => '',
		'oxxo' => PaymentMethod::CASH,
		'payid' => '',
		'paymaya' => '',
		'paypal' => PaymentMethod::PAYPAL,
		'paypalpaylater' => PaymentMethod::PAYPAL,
		'payto' => '',
		'pix' => PaymentMethod::CASH,
		'pse' => PaymentMethod::BT,
		'pulse' => PaymentMethod::CC,
		'rabbitlinepay' => '',
		'razorpay' => '',
		'rupay' => PaymentMethod::CC,
		'scalapay' => '',
		'sepa' => PaymentMethod::RTBT,
		'shopeepay' => '',
		'singteldash' => '',
		'smartpay' => '',
		'sofort' => PaymentMethod::RTBT,
		'star' => PaymentMethod::CC,
		'stripedd' => PaymentMethod::STRIPE,
		'thaiqr' => '',
		'touchngo' => '',
		'truemoney' => '',
		'trustly' => PaymentMethod::DD,
		'trustlyeurope' => PaymentMethod::DD,
		'trustlyus' => PaymentMethod::DD,
		'uatp' => PaymentMethod::CC,
		'unionpay' => PaymentMethod::CC,
		'venmo' => PaymentMethod::VENMO,
		'vipps' => '',
		'visa' => PaymentMethod::CC,
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
			case PaymentMethod::EW:
				$payment_submethod = self::$ewSubmethods[$scheme];
				break;
			case PaymentMethod::RTBT:
				$payment_submethod = self::$rtbtSubmethods[$scheme];
				break;
			case PaymentMethod::CC:
			case PaymentMethod::APPLE:
			case PaymentMethod::GOOGLE:
				$payment_submethod = self::$cardPaymentSubmethods[$scheme];
				break;
			case PaymentMethod::DD:
				$payment_submethod = self::$ddSubmethods[$method];
				break;
			case PaymentMethod::BT:
				$payment_submethod = self::$btSubmethods[$method];
				break;
			case PaymentMethod::CASH:
				$payment_submethod = self::$cashSubmethods[$method];
				break;
			default:
				break;
		}

		return [ $payment_method, $payment_submethod ];
	}
}
