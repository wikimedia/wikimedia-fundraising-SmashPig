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

			'refund' => array(
				'class' => 'SmashPig\Core\DataStores\StompDataStore',
				'inst-args' => array( 'refund' ),
			),

			'limbo' => array(
				'class' => 'SmashPig\Core\DataStores\StompDataStore',
				'inst-args' => array( 'limbo' ),
			),

			'jobs-damaged' => array(
				'class' => 'SmashPig\Core\DataStores\StompDataStore',
				'inst-args' => array( 'jobs-damaged' ),
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
				'uri' => 'tcp://localhost:61613',
				'timeout' => 1,
				'refresh-connection' => false,
				'convert-string-expressions' => false,

				'queues' => array(
					'limbo' => '/queue/limbo',
					'verified' => '/queue/donations',
					'failed' => '/queue/failed',
					'pending' => '/queue/pending',
					'refund' => '/queue/refund',
					'jobs' => '/queue/job-requests',
					'jobs-damaged' => '/queue/jobs-damaged',
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
                    /**
                     * For each Adyen merchant account, add an entry like the
                     * following with the merchant account name as the key.
                     * The ws- credentials should be a user authorized to make
                     * API calls, and the report- credentials should be a user
                     * authorized to download reports. Reports will be
                     * downloaded to the location specified in report-location
                     * and moved to report-archive-location after processing.
                     *
                     * At least one account and all subkeys are required.
                     *
                     * 'example-account-name' => array(
                     *   'ws-username' => '',
                     *   'ws-password' => '',
                     *   'report-username' => '',
                     *   'report-password' => '',
                     *   'report-location' => '/tmp',
                     *	 'report-archive-location' => '/var/spool/audit/adyen/completed',
                     * )
                     **/
                ),
            ),
        ),

		'actions' => array( ),
    ),

	'amazon' => array(
		'actions' => array(
			'SmashPig\PaymentProviders\Amazon\Actions\CloseOrderReference',
			'SmashPig\PaymentProviders\Amazon\Actions\AssociateRefundParent',
			'SmashPig\PaymentProviders\Amazon\Actions\AddMessageToQueue',
		),

		'endpoints' => array(
			'listener' => array(
				'class' => 'SmashPig\PaymentProviders\Amazon\AmazonListener',
				'inst-args' => array(),
			)
		),

		// need an array within an array to give clients get a single
		// constructor argument
		'credentials' => array( array(
			'merchant_id' => '', // 13 or so uppercase letters
			'client_id' => '', // app or site-specific, starts with amznX.application
			'access_key' => '', // 20 alphanumeric characters
			'secret_key' => '', // 40 base-64 encoded chars
			'region' => '', // 'de', 'jp', 'uk', or 'us'
			'sandbox' => false,
		) ),

		// Override these to mock the Amazon SDK classes
		'payments-client' => array(
			'class' => 'PayWithAmazon\PaymentsClient',
			'inst-args@' => 'credentials'
		),
		'reports-client' => array(
			'class' => 'PayWithAmazon\ReportsClient',
			'inst-args@' => 'credentials'
		),
		'ipn-handler-class' => 'PayWithAmazon\IpnHandler',

		'audit' => array (
			'download-path' => '',
			'archive-path' => '',
			'file-types' => array(
				'SmashPig\PaymentProviders\Amazon\Audit\RefundReport',
				'SmashPig\PaymentProviders\Amazon\Audit\SettlementReport',
			),
		),
	),

	'astropay' => array(
		'actions' => array(
			'SmashPig\PaymentProviders\AstroPay\Actions\IncomingMessage',
		),

		'endpoints' => array(
			'listener' => array(
				'class' => 'SmashPig\PaymentProviders\AstroPay\AstroPayListener',
				'inst-args' => array(),
			),
		),
		'login' => 'createlogin',
		'secret' => 'secretkey',
		'charset' => 'iso-8859-1',
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
