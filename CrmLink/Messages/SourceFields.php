<?php
namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\Context;

class SourceFields {
	/**
	 * @param array $message
	 */
	public static function addToMessage( &$message ) {
		$message['source_name'] = 'SmashPig';
		$message['source_type'] = 'listener';
		$message['source_host'] = gethostname();
		$message['source_run_id'] = getmypid();
		$message['source_version'] = Context::get()->getSourceRevision();
		$message['source_enqueued_time'] = time();
	}
}
