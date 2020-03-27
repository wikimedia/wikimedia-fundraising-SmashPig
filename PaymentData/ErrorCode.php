<?php

namespace SmashPig\PaymentData;

/**
 * Constants to indicate types of errors from payment gateways.
 * Reflects the failure patterns of gateways we have integrated with.
 * TO CONSIDER: should these constants just be properties of PaymentError?
 */
class ErrorCode {
	/** @var string API authentication failure */
	const BAD_SIGNATURE = 1000000;
	/** @var string Normal decline code. FIXME this isn't really an error, is it? */
	const DECLINED = 1000001;
	/** @var string Card declined on suspected villainy - DO NOT RETRY! */
	const DECLINED_DO_NOT_RETRY = 1000002;
	/** @var string We screwed up and reused our identifier. Can increment and retry. */
	const DUPLICATE_ORDER_ID = 1000003;
	/** @var string We expect a txn ID in the processor response, but it's not there! */
	const MISSING_TRANSACTION_ID = 1000004;
	/** @var string Something else essential is missing */
	const MISSING_REQUIRED_DATA = 1000005;
	/** @var string The whole dang response is missing */
	const NO_RESPONSE = 1000006;
	/** @var string Their server tells us that it has timed out */
	const SERVER_TIMEOUT = 1000007;
	/** @var string A supposedly well-defined field has a value we don't know what to do with */
	const UNEXPECTED_VALUE = 1000008;
	/** @var string For use in default: cases. When encountered, classify the error and add here if needed */
	const UNKNOWN = 1000009;
}
