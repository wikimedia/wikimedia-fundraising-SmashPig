<?php
namespace SmashPig\Core;

/**
 * Represents a validation error associated with a field or the 'general' bucket.
 */
class ValidationError {
	/**
	 * @var string normalized name of field with the error
	 */

	protected $field;
	/**
	 * @var string|null A key indicating which message should be displayed to the user
	 */
	protected $messageKey;

	/**
	 * @var array any parameters that should be interpolated into the displayed message
	 */
	protected $messageParams;

	/**
	 * ValidationError constructor.
	 * @param string $field normalized field name
	 * @param string|null $messageKey i18n key for the error message
	 * @param array $messageParams parameters to interpolate into the message
	 */
	public function __construct( string $field, ?string $messageKey = null, array $messageParams = [] ) {
		$this->field = $field;
		$this->messageKey = $messageKey;
		$this->messageParams = $messageParams;
	}

	public function getField(): string {
		return $this->field;
	}

	public function getMessageKey(): ?string {
		return $this->messageKey;
	}

	public function setMessageKey( ?string $key ) {
		$this->messageKey = $key;
	}

	public function getMessageParams(): array {
		return $this->messageParams;
	}
}
