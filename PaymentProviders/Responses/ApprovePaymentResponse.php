<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Class ApprovePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class ApprovePaymentResponse extends PaymentProviderResponse {

	/**
	 * Net amount, may have been converted to settlement currency
	 * @var float|null
	 */
	protected ?float $settledAmount = null;

	/**
	 * Settlement currency
	 * @var string|null
	 */
	protected ?string $settledCurrency = null;

	/**
	 * @var float|null
	 */
	protected ?float $fee = null;

	/**
	 * If not given, fee is assumed to be in the settlement currency
	 * @var string|null
	 */
	protected ?string $feeCurrency = null;
}
