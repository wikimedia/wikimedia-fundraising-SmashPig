<?php

namespace SmashPig\PaymentProviders\dlocal;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponseFactory;
use UnexpectedValueException;

class DlocalCreatePaymentResponseFactory extends CreatePaymentResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return CreatePaymentResponse
	 */
	public static function fromRawResponse( $rawResponse ): CreatePaymentResponse {
		$createPaymentResponse = new CreatePaymentResponse();
		$createPaymentResponse->setRawResponse( $rawResponse );
		$rawStatus = $rawResponse['status'] ?? '';
		$gatewayTxnId = $rawResponse['id'] ?? null;
		$createPaymentResponse->setRawStatus( $rawStatus );
		if ( $gatewayTxnId ) {
			$createPaymentResponse->setGatewayTxnId( $gatewayTxnId );
		}
		if ( array_key_exists( 'redirect_url', $rawResponse ) ) {
			$createPaymentResponse->setRedirectUrl( $rawResponse['redirect_url'] );
		}
		$statusMapper = new CreatePaymentStatusNormalizer();
		try {
			$status = $statusMapper->normalizeStatus( $rawStatus );
			$createPaymentResponse->setStatus( $status );
			if ( $status === FinalStatus::FAILED ) {
				$createPaymentResponse->addErrors( new PaymentError( ErrorMapper::$errorCodes[ $rawResponse[ 'status_code' ] ],
					$rawResponse[ 'status_detail' ], LogLevel::ERROR ) );
				$createPaymentResponse->setSuccessful( false );
			} else {
				$createPaymentResponse->setSuccessful( $statusMapper->isSuccessStatus( $status ) );
			}
		} catch ( UnexpectedValueException $ex ) {
			$createPaymentResponse->addErrors( new PaymentError( ErrorCode::UNEXPECTED_VALUE, $ex->getMessage(), LogLevel::ERROR ) );
			Logger::debug( 'Unable to map dlocal status', $rawResponse );
			$createPaymentResponse->setSuccessful( false );
			$createPaymentResponse->setStatus( FinalStatus::UNKNOWN );
		}

		return $createPaymentResponse;
	}
}
