<?php namespace SmashPig\CrmLink\Messages;

/**
 * Message encapsulating fraud scores and outcome
 */
class DonationInterfaceAntifraudFactory {

	public static function create(
		$donationMessage,
		$riskScore,
		$scoreBreakdown = [],
		$validationAction = 'process'
	) {
		$antifraud = [
			'risk_score' => $riskScore,
			'score_breakdown' => $scoreBreakdown,
			'validation_action' => $validationAction,
		];

		$keysToCopy = [
			'contribution_tracking_id',
			'date',
			'gateway',
			'order_id',
			'payment_method',
			'user_ip'
			// no 'server' available
		];

		foreach ( $keysToCopy as $key ) {
			$antifraud[$key] = $donationMessage[$key];
		}

		return $antifraud;
	}
}
