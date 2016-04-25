<?php

$config_defaults = array(
	'default' => array(
		'data-store' => array(
			// Store definitions
			'inflight' => array(
				'class' => 'SmashPig\Core\DataStores\DiskFileDataStore',
				'inst-args' => array( '/tmp/' ),
			),

			'antifraud' => array(
				'class' => 'SmashPig\Core\DataStores\StompDataStore',
				'inst-args' => array( 'antifraud' ),
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
					'antifraud' => '/queue/payments-antifraud',
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
			'from-address' => array( 'sender@contoso.com', 'Example Sender' ),
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
				'api' => array(
					'class' => 'SmashPig\PaymentProviders\Adyen\AdyenPaymentsAPI',
					'inst-args' => array(),
				),

				'payments-wsdl' => 'https://pal-live.adyen.com/pal/Payment.wsdl',

				'accounts' => array(
					/**
					 * For each Adyen merchant account, add an entry like the
					 * following with the merchant account name as the key.
					 * The ws- credentials should be a user authorized to make
					 * API calls, and the report- credentials should be a user
					 * authorized to download reports. Reports will be
					 * downloaded to the location specified in report-location.
					 *
					 * At least one account and all subkeys are required.
					 *
					 * 'example-account-name' => array(
					 *   'ws-username' => '',
					 *   'ws-password' => '',
					 *   'report-username' => '',
					 *   'report-password' => '',
					 *   'report-location' => '/tmp',
					 * )
					 **/
				),
			),
		),

		'fraud-filters' => array(
			// Automatically capture authorizations under this score
			'review-threshold' => 75,
			// Automatically cancel authorizations with at least this score
			'reject-threshold' => 100,
			/**
			 * Authorization notifications include AVS and CVV result codes.
			 * The following maps set a risk score for each result code, which
			 * we combine with any risk score computed on the payment site to
			 * decide whether to capture the payment or leave it for review.
			 * https://docs.adyen.com/manuals/api-manual/payment-responses/additionaldata-payment-responses
			 */
			'avs-map' => array(
				'0' => 100, // Unknown
				'1' => 75, // Address matches, postal code doesn't
				'2' => 100, // Neither postal code nor address match
				'3' => 50, // AVS unavailable
				'4' => 50, // AVS not supported for this card type
				'5' => 50, // No AVS data provided
				'6' => 75, // Postal code matches, address doesn't match
				'7' => 0, // Both postal code and address match
				'8' => 50, // Address not checked, postal code unknown
				'9' => 25, // Address matches, postal code unknown
				'10' => 50, // Address doesn't match, postal code unknown
				'11' => 75, // Postal code not checked, address unknown
				'12' => 50, // Address matches, postal code not checked
				'13' => 0, // Address doesn't match, postal code not checked
				'14' => 50, // Postal code matches, address unknown
				'15' => 50, // Postal code matches, address not checked
				'16' => 75, // Postal code doesn't match, address unknown
				'17' => 100, // Postal code doesn't match, address not checked
				'18' => 25, // Neither postal code nor address were checked
				'19' => 0, // Name and postal code matches
				'20' => 0, // Name, address and postal code matches
				'21' => 0, // Name and address matches
				'22' => 50, // Name matches
				'23' => 50, // Postal code matches, name doesn't match
				'24' => 25, // Both postal code and address matches, name doesn't match
				'25' => 50, // Address matches, name doesn't match
				'26' => 100, // Neither postal code, address nor name matches
			),
			'cvv-map' => array(
				'0' => 100, // Unknown
				'1' => 0, // Matches
				'2' => 100, // Doesn't match
				'3' => 50, // Not checked
				'4' => 100, // No CVC/CVV provided, but was required
				'5' => 50, // Issuer not certified for CVC/CVV
				'6' => 50, // No CVC/CVV provided
			),
		),

		'actions' => array(),
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

		'audit' => array(
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
