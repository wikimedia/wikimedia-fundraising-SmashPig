<?php

namespace SmashPig\PaymentProviders\dlocal\Tests\phpunit;

use DateTime;
use DateTimeZone;
use Monolog\Test\TestCase;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\ApiMappers\HostedPaymentApiRequestMapper;

/**
 * @group Dlocal
 * @group DlocalMapperTest
 */
class HostedPaymentApiRequestMapperTest extends TestCase {

	public function testInitializePaymentApiRequestMapper(): void {
		$class = new HostedPaymentApiRequestMapper();
		$this->assertInstanceOf( HostedPaymentApiRequestMapper::class, $class );
	}

	public function testHostedPaymentApiRequestMapperTransformInputToExpectedOutput(): void {
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$expectedOutput = $params['transformedParams'];
		$apiRequestMapper = new HostedPaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );

		$this->assertEquals( $expectedOutput, $apiRequestMapper->getAll() );
	}

	public function testHostedPaymentApiRequestMapperTransformRecurringInputToExpectedOutput(): void {
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$apiParams['recurring'] = 1;
		$expectedOutput = $params['transformedParams'];

		$subscriptionStartDate = ( new DateTime( 'now', new DateTimeZone( Api::INDIA_TIME_ZONE ) ) )->format( 'Ymd' );
		$subscriptionEndDate = '20991231';

		$expectedOutput['wallet'] = [
			'save' => true,
			'capture' => true,
			'verify' => false,
			'username' => $expectedOutput['payer']['name'],
			'email' => $expectedOutput['payer']['email'],
			'recurring_info' => [
				'prenotify' => true,
				'subscription_frequency_unit' => Api::SUBSCRIPTION_FREQUENCY_UNIT,
				'subscription_frequency' => 1,
				'subscription_start_at' => $subscriptionStartDate,
				'subscription_end_at' => $subscriptionEndDate
			],
		];
		$apiRequestMapper = new HostedPaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );

		$this->assertEquals( $expectedOutput, $apiRequestMapper->getAll() );
	}

	private function getBaseParams(): array {
		$input = [
			'order_id' => '123.3',
			'amount' => '100',
			'currency' => 'MXN',
			'country' => 'MX',
			'first_name' => 'Lorem',
			'last_name' => 'Ipsum',
			'email' => 'li@mail.com',
			'fiscal_number' => '12345',
			'contact_id' => '12345',
			'state_province' => 'lore',
			'city' => 'lore',
			'postal_code' => 'lore',
			'street_address' => 'lore',
			'street_number' => 2,
			'user_ip' => '127.0.0.1',
		];
		$transformedParams = [
			'amount' => $input['amount'],
			'currency' => $input['currency'],
			'country' => $input['country'],
			'order_id' => $input['order_id'],
			'payment_method_flow' => Api::PAYMENT_METHOD_FLOW_REDIRECT,
			'payer' => [
				'name' => $input['first_name'] . ' ' . $input['last_name'],
				'email' => $input['email'],
				'document' => $input['fiscal_number'],
				'user_reference' => $input['contact_id'],
				'ip' => $input['user_ip'],
				'address' => [
					'state' => $input['state_province'],
					'city' => $input['city'],
					'zip_code' => $input['postal_code'],
					'street' => $input['street_address'],
					'number' => $input['street_number'],
				],
			],
			'wallet' => [
				'recurring_info' => [
					'prenotify' => true,
				],
			],
		];

		return [
			'params' => $input,
			'transformedParams' => $transformedParams
		];
	}
}