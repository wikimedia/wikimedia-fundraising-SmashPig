<?php
namespace SmashPig\Core;

/**
 * Represents a validation error associated with a field or the 'general' bucket.
 */
class ValidationError {
	protected $field;
	protected $messageKey;
	protected $messageParams;

	/**
	 * ValidationError constructor.
	 * @param string $field normalized field name
	 * @param string $messageKey i18n key for the error message
	 * @param array $messageParams parameters to interpolate into the message
	 */
	public function __construct( $field, $messageKey, $messageParams = [] ) {
		$this->field = $field;
		$this->messageKey = $messageKey;
		$this->messageParams = $messageParams;
	}

	public function getField() {
		return $this->field;
	}

	public function getMessageKey() {
		return $this->messageKey;
	}

	public function getMessageParams() {
		return $this->messageParams;
	}
}
