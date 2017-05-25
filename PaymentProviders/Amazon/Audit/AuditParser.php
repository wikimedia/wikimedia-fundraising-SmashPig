<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

use SmashPig\Core\Context;

/**
 * Parses off-Amazon payments reports retrieved from MWS
 */
class AuditParser {

	public function parseFile( $path ) {
		$config = Context::get()->getProviderConfiguration();
		$fileTypes = $config->val( 'audit/file-types' );

		$data = array();

		foreach ( $fileTypes as $type ) {
			if ( $type::isMine( $path ) ) {
				$parser = new $type();
				$data = $parser->parse( $path );
			}
		}

		return $data;
	}
}
