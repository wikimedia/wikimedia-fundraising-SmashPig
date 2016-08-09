<?php namespace SmashPig\Core;

use DateTime;
use DateTimeZone;
use Exception;
use SmashPig\Core\Logging\Logger;

class UtcDate {
	// FIXME: Should probably let the exception bubble up instead of setting
	// dates to null.
	public static function getUtcTimestamp( $dateString, $timeZone = 'UTC' ) {
		try {
			$obj = new DateTime( $dateString, new DateTimeZone( $timeZone ) );
			return $obj->getTimestamp();
		} catch ( Exception $ex ) {
			Logger::warning( 'Could not get timestamp from string', $dateString, $ex );
			return null;
		}
	}

	/**
	 * Format a UTC timestamp for database insertion
	 * @param int|null $timestamp, defaults to time()
	 * @param string $format optional time format
	 * @return string
	 * @throws Exception
	 */
	public static function getUtcDatabaseString(
		$timestamp = null, $format = 'YmdHis'
	) {
		if ( $timestamp === null ) {
			$timestamp = time();
		}
		$obj = new DateTime( '@' . $timestamp, new DateTimeZone( 'UTC' ) );
		return $obj->format( $format );
	}
}
