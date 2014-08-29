<?php

namespace SmashPig\PaymentProviders\WorldPay\Audit;

class WorldPayAudit {

	function retrieveFiles() {
		AuditRetriever::retrieveAll( 'worldpay' );
	}

	function parseFile( $file ) {
		// FIXME: this should be specified in configuration
		$fileTypes = array(
			'SmashPig\PaymentProviders\WorldPay\Audit\TransactionReconciliationFile',
		);

		foreach ( $fileTypes as $type ) {
			if ( $type::isMine( $file ) ) {
				$parser = new $type();
				$data = $parser->parse( $file );
			}
		}

		return $data;
	}

}
