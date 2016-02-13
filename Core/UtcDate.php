<?php namespace SmashPig\Core;

use DateTime;
use DateTimeZone;
use Exception;
use SmashPig\Core\Logging\Logger;

class UtcDate {
	public static function getUtcTimestamp( $dateString, $timeZone = 'UTC' ) {
		try {
			$obj = new DateTime( $dateString, new DateTimeZone( $timeZone ) );
			return $obj->getTimestamp();
		} catch ( Exception $ex ) {
			Logger::warning ( 'Caught date exception: ' . $ex->getMessage(), $dateString );
			return null;
		}
	}
}
