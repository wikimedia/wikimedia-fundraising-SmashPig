<?php
namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\Context;
use SmashPig\Core\UtcDate;

class SourceFields {
	/**
	 * @param array $message
	 */
	public static function addToMessage( &$message ) {
		$context = Context::get();
		$message['source_name'] = $context->getSourceName();
		$message['source_type'] = $context->getSourceType();
		$message['source_host'] = gethostname();
		$message['source_run_id'] = getmypid();
		$message['source_version'] = $context->getSourceRevision();
		$message['source_enqueued_time'] = UtcDate::getUtcTimestamp();
	}
}
