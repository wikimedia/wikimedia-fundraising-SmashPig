<?php
namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\SmashPigException;

/**
 * Class HostedCheckoutProvider
 *
 * @package SmashPig\PaymentProviders\Ingenico
 */
class HostedCheckoutProvider extends PaymentProvider {
	/**
	 * @var string subdomain
	 */
	protected $subdomain;

	/**
	 * HostedCheckoutProvider constructor.
	 * @param array $options
	 * @throws SmashPigException
	 */
	public function __construct( array $options = [] ) {
		parent::__construct( $options );
		if ( array_key_exists( 'subdomain', $options ) ) {
			$this->subdomain = $options['subdomain'];
		} else {
			throw new SmashPigException( "Subdomain key missing from configuration." );
		}
	}

	/**
	 * @param $params
	 *
	 * @return mixed
	 */
	public function createHostedPayment( $params ) {
		$path = 'hostedcheckouts';
		$response = $this->api->makeApiCall( $path, 'POST', $params );
		return $response;
	}

	/**
	 * @param $hostedPaymentId
	 *
	 * @return mixed
	 */
	public function getHostedPaymentStatus( $hostedPaymentId ) {
		$path = "hostedcheckouts/$hostedPaymentId";
		$response = $this->api->makeApiCall( $path, 'GET' );
		$this->addPaymentStatusErrorsIfPresent( $response,  $response['createdPaymentOutput']['payment'] );
		return $response;
	}

	/**
	 * @param $partialRedirectUrl
	 *
	 * @return string
	 */
	public function getHostedPaymentUrl( $partialRedirectUrl ) {
		return "https://{$this->subdomain}.$partialRedirectUrl";
	}
}
