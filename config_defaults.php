<?php

$config_defaults = array(
	'default' => array(
		'data-store' => array(
			// Store definitions
			'inflight' => array(
				'class' => 'SmashPig\Core\DataStores\DiskFileDataStore',
				'inst-args' => array( '/tmp/' ),
			),

			'pending' => array(
				'class' => 'SmashPig\Core\DataStores\StompDataStore',
				'inst-args' => array( 'pending' ),
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
				'refresh-connection' => false,
				'convert-string-expressions' => false,

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
					'class' => 'SmashPig\Core\Logging\LogStreams\SyslogLogStream',
					'inst-args' => array( LOG_LOCAL0, LOG_NDELAY ),
				)
			),
		),

		'security' => array(
            'ip-header-name' => '',
			'ip-trusted-proxies' => array(),
			'ip-whitelist' => array(),
		),

        'endpoints' => array(),

        'namespaces' => array(),

		'include-files' => array(),

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

		'actions' => array( ),
    ),

	'amazon' => array(
		'actions' => array(
			'SmashPig\PaymentProviders\Amazon\Actions\IncomingMessage',
		),

		'endpoints' => array(
			'listener' => array(
				'class' => 'SmashPig\PaymentProviders\Amazon\AmazonListener',
				'inst-args' => array(),
			)
		),
	),

	'astropay' => array(
		'actions' => array(
			'SmashPig\PaymentProviders\Astropay\Actions\IncomingMessage',
		),

		'endpoints' => array(
			'listener' => array(
				'class' => 'SmashPig\PaymentProviders\Astropay\AstropayListener',
				'inst-args' => array(),
			),
		),
		'login' => 'createlogin',
		'secret' => 'secretkey',
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
		'actions' => array(
			'SmashPig\PaymentProviders\GlobalCollect\Actions\IncomingMessage',
		),

		'endpoints' => array(
			'listener' => array(
				'class' => 'SmashPig\PaymentProviders\GlobalCollect\GlobalCollectListener',
				'inst-args' => array(),
			)
		),

		'data-store' => array(
			'stomp' => array(
				'queues' => array(
					'pending' => '/queue/pending_globalcollect',
				),
			),
		),
	),
);
