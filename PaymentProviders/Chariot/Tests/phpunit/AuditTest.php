<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Chariot\Audit\DonationsAudit;

class AuditTest extends TestCase {

	/**
	 * @dataProvider provideAuditCsvFiles
	 */
	public function testParseFileReturnsParsedRows( string $filePath ): void {
		$parser = new DonationsAudit();
		$rows = $parser->parseFile( $filePath );

		$this->assertIsArray( $rows );
		$this->assertNotEmpty( $rows, 'Expected parsed rows for fixture ' . basename( $filePath ) );

		foreach ( $rows as $row ) {
			$this->assertIsArray( $row );
			$this->assertArrayHasKey( 'gateway', $row );
			$this->assertArrayHasKey( 'audit_file_gateway', $row );
			$this->assertArrayHasKey( 'type', $row );
			$this->assertArrayHasKey( 'date', $row );

			$this->assertSame( 'Chariot Disbursements', $row['gateway'] );
			$this->assertSame( 'Chariot Disbursements', $row['audit_file_gateway'] );
			$this->assertIsInt( $row['date'] );

			if ( array_key_exists( 'settled_date', $row ) ) {
				$this->assertIsInt( $row['settled_date'] );
			}
		}
	}

	/**
	 * @dataProvider provideAuditCsvFiles
	 */
	public function testParseFileIncludesExpectedColumnsWhenPresent( string $filePath ): void {
		$parser = new DonationsAudit();
		$rows = $parser->parseFile( $filePath );

		$allKeys = [];
		foreach ( $rows as $row ) {
			foreach ( array_keys( $row ) as $key ) {
				$allKeys[$key] = true;
			}
		}

		$this->assertArrayHasKey( 'backend_processor', $allKeys );
		$this->assertArrayHasKey( 'backend_processor_txn_id', $allKeys );
		$this->assertArrayHasKey( 'currency', $allKeys );
		$this->assertArrayHasKey( 'settlement_batch_reference', $allKeys );
		$this->assertArrayHasKey( 'payment_method', $allKeys );
	}

	public function testParseFilePreservesMatchingGiftFields(): void {
		$fixture = $this->getDataDirectory() . '/2026-03-23-123147-Benevity-146.66-deposit_01km760bp91vxmfb4jfs4rbj6c.csv';

		if ( !file_exists( $fixture ) ) {
			$this->markTestSkipped( 'Matching gift fixture not present.' );
		}

		$parser = new DonationsAudit();
		$rows = $parser->parseFile( $fixture );

		$foundDonationRow = false;
		foreach ( $rows as $row ) {
			if ( ( $row['type'] ?? '' ) !== 'donation' ) {
				continue;
			}

			$foundDonationRow = true;
			$this->assertArrayHasKey( 'match_total_amount', $row );

			if ( array_key_exists( 'matching_gift_organization', $row ) ) {
				$this->assertIsString( $row['matching_gift_organization'] );
			}
			if ( array_key_exists( 'matching_gift_source', $row ) ) {
				$this->assertIsString( $row['matching_gift_source'] );
			}
		}

		$this->assertTrue( $foundDonationRow, 'Expected at least one donation row in matching gift fixture.' );
	}

	public static function provideAuditCsvFiles(): array {
		$dir = __DIR__ . '/Data';
		$files = glob( $dir . '/*.csv' ) ?: [];

		$cases = [];
		foreach ( $files as $file ) {
			$cases[basename( $file )] = [ $file ];
		}

		return $cases;
	}

	private function getDataDirectory(): string {
		return __DIR__ . '/Data';
	}
}
