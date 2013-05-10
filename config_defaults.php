<?php

$config_defaults = array(
	'default' => array(
		'data-store' => array(
			// Store definitions
			'pending' => array(
				'class' => 'SmashPig\Core\DataStores\DiskFileDataStore',
				'inst-args' => array( '/tmp/' ),
			),

			'limbo' => array(
				'class' => 'SmashPig\Core\DataStores\StompDataStore',
				'inst-args' => array( 'limbo' ),
			),

			'jobs' => array(
				'class' => 'SmashPig\Core\DataStores\StompDataStore',
				'inst-args' => array( 'jobs' ),
			),

			'verified' => array(
				'class' => 'SmashPig\Core\DataStores\StompDataStore',
				'inst-args' => array( 'verified' ),
			),

			// Library definitions
			'stomp' => array(
				'lib-path' => '',

				'uri' => 'tcp://localhost:61613',
				'timeout' => 1,

				'queues' => array(
					'limbo' => '/queue/limbo',
					'verified' => '/queue/donations',
					'failed' => '/queue/failed',
					'pending' => '/queue/pending',
					'jobs' => '/queue/job-requests',
				),
			),
		),

		'logging' => array(
            'root-context' => 'SmashPig',
			'log-level' => LOG_INFO,
			'enabled-log-streams' => array(
				'syslog',
			),
			'log-streams' => array(
				'syslog' => array(
					'class' => 'SmashPig\Core\Logging\SyslogLogStream',
					'inst-args' => array( LOG_LOCAL0, LOG_NDELAY ),
				)
			),
		),

		'security' => array(
            'ip-header-name' => '',
			'ip-whitelist' => array(),
		),

        'endpoints' => array(),

        'namespaces' => array(),

		'include-paths' => array(),

        'payment-provider' => array(),

		'actions' => array(),

		'email' => array(
			'from-address' => array('sender@contoso.com', 'Example Sender'),
			'bounce-address' => 'bounce+$1@contoso.com',
			'archive-addresses' => array(),
		),
	),

    'adyen' => array(
        'logging' => array(
            'root-context' => 'SmashPig-Adyen'
        ),

        'endpoints' => array(
            'listener' => array(
                'class' => 'SmashPig\PaymentProviders\Adyen\AdyenListener',
                'inst-args' => array(),
            )
        ),

        'payment-provider' => array(
            'adyen' => array(
                'payments-wsdl' => 'https://pal-live.adyen.com/pal/Payment.wsdl',

                'accounts' => array(
                    /* 'account-name' => array(
                        'ws-username' => '',
                        'ws-passowrd' => '',
                    )
                    */
                ),
            ),
        ),

		'actions' => array(
			'SmashPig\PaymentProviders\Adyen\Actions\PaymentCaptureAction',
		)
    ),

	'paypal' => array(
		'listener' => array(
			// For testing purposes override this config.php to
			// 'postback-url' => https://www.sandbox.paypal.com/cgi-bin/webscr
			'postback-url' => 'https://www.paypal.com/cgi-bin/webscr',
		),

		'data-store' => array(
			'stomp' => array(
				'queues' => array(
					'pending' => '/queue/pending_paypal',
				),
			),
		),
	),

	'globalcollect' => array(
		'data-store' => array(
			'stomp' => array(
				'queues' => array(
					'pending' => '/queue/pending_globalcollect',
				),
			),
		),
	),
);
