<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Chariot\Donation;

class DonationTest extends TestCase {

	/**
	 * @param array $donation
	 *
	 * @dataProvider partnerDataProvider
	 *
	 * @return void
	 */
	public function testGetPartnerName( array $donation ): void {
		$this->assertEquals( 'Mickey Mouse', ( new Donation( $donation ) )->getPartnerName() );
	}

	public function partnerDataProvider(): array {
		return [
			'set_in_property' => [
				'donation' => [
					'properties' => [ 'Partner' => 'Mickey Mouse' ],
				],
			],
			'paypal_grants_style' => [
				'donation' => [
					'attribution' => [
						'joint_donor' => [
							'email' => '',
							'full_name' => 'Mickey Mouse',
						],
						'primary_donor' => [
							'email' => '',
							'full_name' => 'Minnie Mouse',
						],
					],
				],
			],
		];
	}

}
