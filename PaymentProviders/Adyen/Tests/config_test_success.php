<?php

$config = array(
	'adyen' => array(
		'data-store' => array(
			'pending' => array(
				'class' => 'MockDataStore',
				'inst-args' => array(),
			),
			'antifraud' => array(
				'class' => 'MockDataStore',
				'inst-args' => array(),
			),
		),
		'payment-provider' => array(
			'adyen' => array(
				'api' => array(
					'class' => 'SmashPig\PaymentProviders\Adyen\Tests\MockAdyenPaymentsAPI',
					'inst-args' => array( 'Success!' ),
				),
			),
		)
	)
);
