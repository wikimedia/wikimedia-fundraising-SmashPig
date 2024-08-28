<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Class ApprovePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class ApprovePaymentResponse extends PaymentProviderResponse {

	/**
	 * Net amount, may have been converted to settlement currency
	 * @var numeric|null
	 */
	protected $settledAmount;

	/**
	 * Settlement currency
	 * @var string|null
	 */
	protected ?string $settledCurrency = null;

	/**
	 * @var numeric|null
	 */
	protected $fee;

	/**
	 * If not given, fee is assumed to be in the settlement currency
	 * @var string|null
	 */
	protected ?string $feeCurrency = null;
}
