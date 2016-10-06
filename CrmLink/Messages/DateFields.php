<?php
namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\UtcDate;

class DateFields {

	/**
	 * @param array $message A message from donation queues
	 * @return int The unix timestamp at which the message was originally
	 *  enqueued, or the current timestamp if no date information exists
	 */
	public static function getOriginalDateOrNow( $message ) {
		// This is the actual queued time
		if ( isset( $message['source_enqueued_time'] ) ) {
			// This is only ever set to the numeric timestamp
			return $message['source_enqueued_time'];
		}
		// Message missing source field might still have a date
		if ( isset( $message['date'] ) ) {
			// This field is sometimes not a timestamp
			// FIXME: normalize PayPal recurring before queueing!
			if ( is_integer( $message['date'] ) ) {
				return $message['date'];
			}
			// Try parsing non-numeric things
			$parsedTimestamp = UtcDate::getUtcTimestamp(
				$message['date']
			);
			if ( !is_null( $parsedTimestamp ) ) {
				return $parsedTimestamp;
			}
		}
		return UtcDate::getUtcTimestamp();
	}
}
