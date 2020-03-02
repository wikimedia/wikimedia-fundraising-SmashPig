<?php

namespace SmashPig\PaymentData;

/**
 * Constants to indicate types of errors from payment gateways.
 * Reflects the failure patterns of gateways we have integrated with.
 * TO CONSIDER: should these constants just be properties of PaymentError?
 */
class ErrorCode {
	/** @var string API authentication failure */
	const BAD_SIGNATURE = 'BAD_SIGNATURE';
	/** @var string Normal decline code. FIXME this isn't really an error, is it? */
	const DECLINED = 'DECLINED';
	/** @var string Card declined on suspected villainy - DO NOT RETRY! */
	const DECLINED_DO_NOT_RETRY = 'DECLINED_DO_NOT_RETRY';
	/** @var string We screwed up and reused our identifier. Can increment and retry. */
	const DUPLICATE_ORDER_ID = 'DUPLICATE_ORDER_ID';
	/** @var string We expect a txn ID in the processor response, but it's not there! */
	const MISSING_TRANSACTION_ID = 'MISSING_TRANSACTION_ID';
	/** @var string Something else essential is missing */
	const MISSING_REQUIRED_DATA = 'MISSING_REQUIRED_DATA';
	/** @var string The whole dang response is missing */
	const NO_RESPONSE = 'NO_RESPONSE';
	/** @var string Their server tells us that it has timed out */
	const SERVER_TIMEOUT = 'SERVER_TIMEOUT';
	/** @var string A supposedly well-defined field has a value we don't know what to do with */
	const UNEXPECTED_VALUE = 'UNEXPECTED_VALUE';
	/** @var string For use in default: cases. When encountered, classify the error and add here if needed */
	const UNKNOWN = 'UNKNOWN';
}
