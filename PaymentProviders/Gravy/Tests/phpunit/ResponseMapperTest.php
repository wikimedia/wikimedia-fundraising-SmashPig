<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class ResponseMapperTest extends BaseGravyTestCase {

	public function testMapToCreatePaymentResponseAuthDecline() {
		$rawResponse = $this->loadTestData( 'trustly-create-transaction-failed.json' );
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertFalse( $result['is_successful'] );
		$this->assertEquals( FinalStatus::FAILED, $result['status'] );
		$this->assertEquals( 'authorization_declined', $result['message'] );
	}

	public function testMapToCreatePaymentResponseAuthDeclineInsufficientFunds() {
		$rawResponse = $this->loadTestData( 'create-payment-response-insufficient-funds.json' );
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertFalse( $result['is_successful'] );
		$this->assertEquals( FinalStatus::FAILED, $result['status'] );
		$this->assertEquals( 'authorization_declined', $result['message'] );
		$this->assertEquals( 'insufficient_funds', $result['description'] );
	}

	public function testMapMotoTransactionSetsMotoFields() {
		$rawResponse = $this->loadTestData( 'moto-transaction-capture-message.json' )['target'];
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertTrue( $result['is_moto'] );
		$this->assertSame( $rawResponse['metadata'], $result['moto_metadata'] );
	}

	public function testMapNonMotoTransactionDoesNotSetMotoFields() {
		// This transaction carries metadata of its own, which should be left alone
		// because it is not a moto gift.
		$rawResponse = $this->loadTestData( 'successful-transaction-capture-message.json' )['target'];
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertArrayNotHasKey( 'is_moto', $result );
		$this->assertArrayNotHasKey( 'moto_metadata', $result );
	}

	public function testMapMotoTransactionWithoutMetadata() {
		$rawResponse = $this->loadTestData( 'moto-transaction-capture-message.json' )['target'];
		unset( $rawResponse['metadata'] );
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertTrue( $result['is_moto'] );
		$this->assertSame( [], $result['moto_metadata'] );
	}

	/**
	 * Helper method to load JSON test data
	 */
	private function loadTestData( string $filename ): array {
		$filePath = __DIR__ . '/../Data/' . $filename;
		$jsonContent = file_get_contents( $filePath );
		return json_decode( $jsonContent, true );
	}
}
