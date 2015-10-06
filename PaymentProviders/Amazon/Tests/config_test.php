<?php

$config = array(
	'amazon' => array(
		'payments-client' => array(
			'class' => 'PayWithAmazon\Mocks\MockPaymentsClient',
			'inst-args' => array( array(
				'response-directory' => __DIR__ . '/Data/responses',
			) ),
		),
		'reports-client' => array(
			'class' => 'PayWithAmazon\Mocks\MockReportsClient',
			'inst-args' => array( array(
				'response-directory' => __DIR__ . '/Data/responses',
			) ),
		),
	)
);
