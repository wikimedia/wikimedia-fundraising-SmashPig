<?php

namespace SmashPig\PaymentProviders\Worldpay\Audit;

class WorldpayAudit {

	function retrieveFiles() {
		AuditRetriever::retrieveAll( 'worldpay' );
	}

	function parseFile( $file ) {
		// FIXME: this should be specified in configuration
		$fileTypes = array(
			'SmashPig\PaymentProviders\Worldpay\Audit\TransactionReconciliationFile',
			'SmashPig\PaymentProviders\Worldpay\Audit\LynkReconciliationFile',
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
