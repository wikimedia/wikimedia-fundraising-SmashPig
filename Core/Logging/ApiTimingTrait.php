<?php

namespace SmashPig\Core\Logging;

use SmashPig\Core\Context;

/**
 * Provides standardised API call timing for payment processor integrations.
 *
 * Usage example:
 *
 *     class Api {
 *         use ApiTimingTrait;
 *
 *         public function createPayment( array $params ): array {
 *             $timedApiMethod = __FUNCTION__;
 *                // wrap the Api call you want to time in timedCall()
 *             return $this->timedCall( $timedApiMethod, function () use ( $params ) {
 *                 // Make API call here
 *                 return $this->client->post( '/payments', $params );
 *             } );
 *         }
 *     }
 *
 * This will emit a log line like:
 *     [processor|paymentMethod|authorize|request|time] 1.234567s
 */
trait ApiTimingTrait {

	/**
	 * Executes a given callable while tracking the execution time for logging purposes.
	 *
	 * @param string $apiMethod The name of the API method being called. This is mapped to a canonical APIOperation value.
	 * @param callable $fn The callable function to be executed.
	 * @param array $context Additional context information to be passed for logging purposes.
	 * @param TaggedLogger|null $logger An optional logger instance for timing and error reporting.
	 */
	protected function timedCall(
		string $apiMethod,
		callable $fn,
		array $context = [],
		?TaggedLogger $logger = null
	) {
		$processorName = $this->getProcessorNameForTimings();
		$operation = ApiOperation::fromProcessorMethod( $processorName, $apiMethod );

		$tag = ApiTimings::buildTag(
			$processorName,
			$this->getPaymentMethodForTimings(),
			$operation->value
		);

		$start = microtime( true );
		try {
			return $fn();
		} finally {
			ApiTimings::log( $tag, microtime( true ) - $start, $context, $logger );
		}
	}

	protected function getProcessorNameForTimings(): string {
		$providerName = Context::get()->getProviderConfiguration()->getProviderName();
		return !empty( $providerName ) ? strtolower( $providerName ) : 'unknown';
	}

	protected function getPaymentMethodForTimings(): string {
		$paymentMethod = Context::get()->getProviderConfiguration()->getPaymentMethod();
		return !empty( $paymentMethod ) ? strtolower( $paymentMethod ) : 'unknown';
	}
}
