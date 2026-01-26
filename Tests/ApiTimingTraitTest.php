<?php

namespace SmashPig\Tests\Logging;

use SmashPig\Core\Logging\ApiTimingTrait;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Timings
 */
class ApiTimingTraitTest extends BaseSmashPigUnitTestCase {

	public function testTimedCallInvokesCallable(): void {
		$o = new class {
			use ApiTimingTrait;

			protected function getProcessorNameForTimings(): string {
				return 'gravy';
			}

			protected function getPaymentMethodForTimings(): string {
				return 'cc';
			}

			public function callTimedCall( string $apiMethod, callable $fn, array $context = [], ?TaggedLogger $logger = null ) {
				return $this->timedCall( $apiMethod, $fn, $context, $logger );
			}
		};

		$result = $o->callTimedCall( 'approvePayment', static fn () => 123 );

		$this->assertSame( 123, $result );
	}

	/**
	 * Verifies that the `timedCall` method uses the canonical operation name
	 * instead of the method name when logging timing-related information.
	 *
	 * @return void
	 */
	public function testTimedCallUsesCanonicalOperationName(): void {
		// Create a mock logger to verify the tag uses a canonical name
		$mockLogger = $this->createMock( TaggedLogger::class );

		// Expect the tag to contain 'capture' (canonical) not 'approvePayment' (method name)
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with(
				$this->stringContains( '[gravy|cc|capture|request|time]' ),
				$this->anything()
			);

		$testClass = new class {
			use ApiTimingTrait;

			protected function getProcessorNameForTimings(): string {
				return 'gravy';
			}

			protected function getPaymentMethodForTimings(): string {
				return 'cc';
			}

			public function approvePayment( ?TaggedLogger $logger = null ) {
				// Pass method name 'approvePayment' which should be mapped to 'capture'
				$this->timedCall( 'approvePayment', static fn () => 'payment-approved', [], $logger );
			}
		};

		$testClass->approvePayment( $mockLogger );
	}

	public function testTimedCallMapsAdyenMethodsToCanonicalNames(): void {
		$mockLogger = $this->createMock( TaggedLogger::class );

		// Expect 'authorize' (canonical) not 'createPaymentFromEncryptedDetails' (method name)
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with(
				$this->stringContains( '[adyen|cc|authorize|request|time]' ),
				$this->anything()
			);

		$testClass = new class {
			use ApiTimingTrait;

			protected function getProcessorNameForTimings(): string {
				return 'adyen';
			}

			protected function getPaymentMethodForTimings(): string {
				return 'cc';
			}

			public function createPaymentFromEncryptedDetails( ?TaggedLogger $logger = null ) {
				$this->timedCall( 'createPaymentFromEncryptedDetails', static fn () => 'authorized', [], $logger );
			}
		};

		$testClass->createPaymentFromEncryptedDetails( $mockLogger );
	}
}
