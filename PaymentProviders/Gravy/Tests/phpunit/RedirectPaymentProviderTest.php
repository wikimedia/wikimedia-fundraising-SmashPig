<?php

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class RedirectPaymentProviderTest extends BaseGravyTestCase {
	/**
	 * @var RedirectPaymentProvider
	 */
	public $provider;

	public function setUp(): void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/venmo' );
	}

	public function testSuccessfulCreatePaymentCreateDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-create-transaction-success.json' ), true );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $responseBody );

		$params = $this->getCreateTrxnParams( $responseBody['amount'] );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "venmo", $response->getPaymentMethod() );
		$this->assertSame( '', $response->getPaymentSubmethod() );
		$this->assertSame( FinalStatus::PENDING, $response->getStatus() );
	}

	public function testSuccessfulAuthorizationVenmo() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-authorize-transaction-successful.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->willReturn( $responseBody );

		$response = $this->provider->getLatestPaymentStatus( [
			'gateway_txn_id' => $responseBody['id']
		] );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "venmo", $response->getPaymentMethod() );
		$this->assertSame( '', $response->getPaymentSubmethod() );
		$this->assertSame( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertSame( $responseBody['payment_method']['label'], $response->getDonorDetails()->getUserName() );
	}

	public function testSuccessfulCreatePaymentCreateDonorNoBuyerApproval() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-create-transaction-success.json' ), true );
		$responseBody['status'] = 'authorization_succeeded';
		$params = [
			'gateway_txn_id' => 'random_txn_id'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->getLatestPaymentStatus( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "venmo", $response->getPaymentMethod() );
		$this->assertSame( '', $response->getPaymentSubmethod() );
		$this->assertEquals( "braintree", $response->getBackendProcessor() );
		$this->assertSame( FinalStatus::PENDING_POKE, $response->getStatus() );
	}

	public function testSuccessfulApprovePaymentCreateDonorSuccessfulAuth() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-approve-transaction-success.json' ), true );
		$params = [
			'gateway_txn_id' => 'random_txn_id'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->getLatestPaymentStatus( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "venmo", $response->getPaymentMethod() );
		$this->assertSame( '', $response->getPaymentSubmethod() );
		$this->assertEquals( "braintree", $response->getBackendProcessor() );
		$this->assertSame( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertSame( $responseBody['payment_method']['label'], $response->getDonorDetails()->getUserName() );
	}

	public function testSuccessfulCreatePaymentFromTokenNoCreateDonorNoGetDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-create-transaction-success.json' ), true );
		$params = $this->getCreateTrxnFromTokenParams( $responseBody['amount'] / 100 );
		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'amount' => $params['amount'] * 100,
				'currency' => $params['currency'],
				'country' => $params['country'],
				'payment_method' => [
					'method' => 'id',
					'id' => $params['recurring_payment_token']
				],
				'payment_source' => 'recurring',
				'is_subsequent_payment' => true,
				'merchant_initiated' => true,
				'external_identifier' => $params['order_id'],
				'buyer_id' => $params['processor_contact_id'],
				"statement_descriptor" => [
					"description" => "Wikimedia Foundation"
				]
			] )
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['payment_service_transaction_id'], $response->getBackendProcessorTransactionId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'],
			$response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testSuccessfulPixRedirect(): void {
		$params = $this->getCreateTrxnParams( 'ABC123-c067-4cd6-a3c8-aec67899d5af' );
		$params['amount'] = '1000';
		$params['currency'] = 'BRL';
		$params['country'] = 'BR';
		$params['fiscal_number'] = '33294576609';
		$params['payment_method'] = "cash";
		$params['payment_submethod'] = "pix";
		$params['return_url'] = "https://localhost:9001/index.php?title=Special:GravyGatewayResult&order_id=296.34&wmf_token=73328ebe66af242c850ac4c695e30150%2B%5C&amount=100.00&currency=BRL&payment_method=cash&payment_submethod=pix&wmf_source=..cash";

		$responseBody = json_decode(
			file_get_contents( __DIR__ . '/../Data/pix-create-transacton-response.json' ),
			true
		);

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
					'amount' => 100000,
					'currency' => 'BRL',
					'country' => 'BR',
					'payment_method' => [
						'method' => 'pix',
						'redirect_url' => 'https://localhost:9001/index.php?title=Special:GravyGatewayResult&order_id=296.34&wmf_token=73328ebe66af242c850ac4c695e30150%2B%5C&amount=100.00&currency=BRL&payment_method=cash&payment_submethod=pix&wmf_source=..cash',
						'country' => 'BR',
						'currency' => 'BRL',
					],
					'external_identifier' => $params['order_id'],
					'buyer' => [
						'external_identifier' => 'lorem@ipsum',
						'billing_details' => [
							'first_name' => 'Lorem',
							'last_name' => 'Ipsum',
							'email_address' => 'lorem@ipsum',
							'phone_number' => null,
							'address' => [
								'city' => null,
								'country' => 'BR',
								'postal_code' => '1234',
								'state' => null,
								'line1' => '10 hopewell street',
								'line2' => null,
								'organization' => 'Wikimedia Foundation',
							],
							'tax_id' => [
								'value' => '33294576609',
								'kind' => 'br.cpf',
							],
						],
					],
					'intent' => 'capture',
					"statement_descriptor" => [
						"description" => "Wikimedia Foundation"
					]
				]
			)
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( 'cash', $response->getPaymentMethod() );
		$this->assertEquals( 'pix', $response->getPaymentSubmethod() );
		$this->assertNotEmpty( $response->getRedirectUrl() );
	}

	public function testSuccessfulOxxoRedirect(): void {
		$params = $this->getCreateTrxnParams( 'ABC123-c067-4cd6-a3c8-aec67899d5af' );
		$params['amount'] = '1000';
		$params['currency'] = 'MXN';
		$params['country'] = 'MX';
		// dlocal tell us to send in a placeholder with 13 digits
		// However, gravy wants it to be a string...
		$params['fiscal_number'] = (string)'1112223334440';
		$params['payment_method'] = "cash";
		$params['payment_submethod'] = "cash_oxxo";
		$params['return_url'] = "https://localhost:9001/index.php?title=Special:GravyGatewayResult&order_id=299.34&wmf_token=73328ebe66af242c850ac4c695e30150%2B%5C&amount=1000.00&currency=MXN&payment_method=cash&payment_submethod=cah_oxxo&wmf_source=..cash";

		$responseBody = json_decode(
			file_get_contents( __DIR__ . '/../Data/oxxo-create-transacton-response.json' ),
			true
		);

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
					'amount' => 100000,
					'currency' => 'MXN',
					'country' => 'MX',
					'payment_method' => [
						'method' => 'oxxo',
						'redirect_url' => 'https://localhost:9001/index.php?title=Special:GravyGatewayResult&order_id=299.34&wmf_token=73328ebe66af242c850ac4c695e30150%2B%5C&amount=1000.00&currency=MXN&payment_method=cash&payment_submethod=cah_oxxo&wmf_source=..cash',
						'country' => 'MX',
						'currency' => 'MXN',
					],
					'external_identifier' => $params['order_id'],
					'buyer' => [
						'external_identifier' => 'lorem@ipsum',
						'billing_details' => [
							'first_name' => 'Lorem',
							'last_name' => 'Ipsum',
							'email_address' => 'lorem@ipsum',
							'phone_number' => null,
							'address' => [
								'city' => null,
								'country' => 'MX',
								'postal_code' => '1234',
								'state' => null,
								'line1' => '10 hopewell street',
								'line2' => null,
								'organization' => 'Wikimedia Foundation',
							],
							'tax_id' => [
								'value' => '1112223334440',
								'kind' => 'mx.curp',
							],
						],
					],
					'intent' => 'capture',
					"statement_descriptor" => [
						"description" => "Wikimedia Foundation"
					]
				]
			)
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( 'cash', $response->getPaymentMethod() );
		$this->assertEquals( 'cash_oxxo', $response->getPaymentSubmethod() );
		$this->assertNotEmpty( $response->getRedirectUrl() );
	}

	private function getCreateTrxnParams( ?string $amount = '1299' ) {
		$params = [];
		$params['country'] = 'US';
		$params['currency'] = 'USD';
		$params['amount'] = $amount;
		$ct_id = mt_rand( 100000, 1000009 );
		$params['order_id'] = "$ct_id.1";
		$params['payment_method'] = "venmo";
		$params['payment_submethod'] = "";
		$params['description'] = "Wikimedia Foundation";

		$donorParams = $this->getCreateDonorParams();
		$params = array_merge( $params, $donorParams );

		return $params;
	}

	private function getCreateTrxnFromTokenParams( $amount ) {
		$params = $this->getCreateTrxnParams( $amount );

		unset( $params['gateway_session_id'] );

		$params['recurring'] = 1;
		$params['recurring_payment_token'] = "random_token";
		$params['processor_contact_id'] = "random_contact_id";
		$params['description'] = "Wikimedia Foundation";
		return $params;
	}
}
