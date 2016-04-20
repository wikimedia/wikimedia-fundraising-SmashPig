<?php
namespace SmashPig\Tests;

use SmashPig\CrmLink\Messages\DonationInterfaceMessage;
use SmashPig\CrmLink\Messages\DonationInterfaceAntifraud;

/**
 * Test CrmLink message functions
 */
class MessageTest extends BaseSmashPigUnitTestCase {

	public function testAntifraudFactory() {
		$diMessage = new DonationInterfaceMessage();
		$diMessage->contribution_tracking_id = mt_rand(0,1000000);
		$diMessage->date = 1455128736;
		$diMessage->gateway = 'adyen';
		$diMessage->gateway_txn_id = mt_rand(0,10000000);
		$diMessage->payment_method = 'cc';
		$diMessage->user_ip = '8.8.4.4';

		$orderId = $diMessage->contribution_tracking_id . '.0';
		$scoreBreakdown = array(
			'getScoreCountry' => 25,
			'getScoreEmailDomain' => 10,
		);
		$afMessage = DonationInterfaceAntifraud::factory(
			$diMessage, $orderId, 12.5, $scoreBreakdown, 'process'
		);

		$this->assertEquals($diMessage->contribution_tracking_id, $afMessage->contribution_tracking_id);
		$this->assertEquals(1455128736, $afMessage->date);
		$this->assertEquals('adyen', $afMessage->gateway);
		$this->assertEquals($diMessage->gateway_txn_id, $afMessage->gateway_txn_id);
		$this->assertEquals($orderId, $afMessage->order_id);
		$this->assertEquals('cc', $afMessage->payment_method);
		$this->assertEquals(12.5, $afMessage->risk_score);
		$this->assertEquals($scoreBreakdown, $afMessage->score_breakdown);
		$this->assertEquals('8.8.4.4', $afMessage->user_ip);
		$this->assertEquals('process', $afMessage->validation_action);
	}
}
