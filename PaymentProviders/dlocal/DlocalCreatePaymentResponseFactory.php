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
		$rawStatus = $rawResponse['status'] ?? null;
		$gatewayTxnId = $rawResponse['id'] ?? null;
		if ( $gatewayTxnId ) {
			$createPaymentResponse->setGatewayTxnId( $gatewayTxnId );
		}
		if ( array_key_exists( 'redirect_url', $rawResponse ) ) {
			$createPaymentResponse->setRedirectUrl( $rawResponse['redirect_url'] );
		}

		try {
			if ( !$rawStatus ) {
				throw new UnexpectedValueException( "Unknown status" );
			}
			$createPaymentResponse->setRawStatus( $rawStatus );
			$statusMapper = new CreatePaymentStatusNormalizer();
			$status = $statusMapper->normalizeStatus( $rawStatus );
			$createPaymentResponse->setStatus( $status );
			if ( $status === FinalStatus::FAILED ) {
				$createPaymentResponse->addErrors( new PaymentError( ErrorMapper::$paymentStatusErrorCodes[ $rawResponse[ 'status_code' ] ],
					$rawResponse[ 'status_detail' ], LogLevel::ERROR ) );
				$createPaymentResponse->setSuccessful( false );
			} else {
				$createPaymentResponse->setSuccessful( $statusMapper->isSuccessStatus( $status ) );
			}
		} catch ( UnexpectedValueException $ex ) {
			Logger::debug( 'Create Payment failed', $rawResponse );

			$code = $rawResponse['code'] ?? null;
			$errorCode = ErrorMapper::$errorCodes[ $code ] ?? null;
			$message = $rawResponse['message'] ?? $ex->getMessage();

			if ( !$errorCode ) {
				Logger::debug( 'Unable to map error code' );
				$errorCode = ErrorCode::UNEXPECTED_VALUE;
			}
			$createPaymentResponse->addErrors( new PaymentError( $errorCode, $message, LogLevel::ERROR ) );
			$createPaymentResponse->setSuccessful( false );
			$createPaymentResponse->setStatus( FinalStatus::UNKNOWN );
		}

		return $createPaymentResponse;
	}
}
