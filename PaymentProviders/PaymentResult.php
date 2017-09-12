<?php

namespace SmashPig\PaymentProviders;

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;

/**
 * Contains donation workflow hints
 *
 * After each donation request or gateway response, the adapter produces
 * a PaymentResult which wraps one of the following:
 *
 *   - Success: Send donor to the Thank You page.
 *
 *   - Failure (unrecoverable): Send donor to the failure page.
 *
 *   - Refresh form: After validation or other recoverable errors, display the
 *     donation form again and give the donor a chance to correct any errors,
 *     usually with helpful notices.  This PaymentResult object will contain
 *     a map of field names to errors.
 *       If we're feel really feisty, we can make the form name dynamic, as
 *     well as other parameters to the view template--so one form may send the
 *     donor to a more appropriate form.
 *
 *   - Iframe: FIXME, this is almost a variation on refreshForm.
 *
 *   - Gateway redirect: Send donor to the gateway, usually with a ton of data
 *     in the URL's GET params.
 */
class PaymentResult {
	protected $iframe;
	protected $form;
	protected $redirect;
	protected $refresh = false;
	protected $errors = array();
	protected $failed = false;

	protected function __construct() {
	}

	public static function newIframe( $name ) {
		$response = new PaymentResult();
		$response->iframe = $name;
		return $response;
	}

	public static function newForm( $name ) {
		$response = new PaymentResult();
		$response->form = $name;
		return $response;
	}

	public static function newRedirect( $url ) {
		$response = new PaymentResult();
		$response->redirect = $url;
		return $response;
	}

	public static function newRefresh( $errors = array() ) {
		$response = new PaymentResult();
		$response->refresh = true;
		$response->errors = $errors;
		return $response;
	}

	public static function newSuccess() {
		$response = new PaymentResult();
		return $response;
	}

	public static function newFailure( $errors = array() ) {
		$response = new PaymentResult();
		$response->failed = true;
		$response->errors = $errors;
		return $response;
	}

	public static function newEmpty() {
		$response = new PaymentResult();
		// FIXME: encapsulated errors, no message
		$response->errors = array( new PaymentError(
			'internal-0000', 'Internal error: no results yet.', LogLevel::ERROR
		) );
		$response->failed = true;
		return $response;
	}

	public function getIframe() {
		return $this->iframe;
	}

	public function getForm() {
		return $this->form;
	}

	public function getRedirect() {
		return $this->redirect;
	}

	public function getRefresh() {
		return $this->refresh;
	}

	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @return bool true when the payment attempt has permanently failed
	 */
	public function isFailed() {
		return $this->failed;
	}

	/**
	 * @return bool true for a successfully completed payment
	 */
	public function isSuccessful() {
		return (
			( !$this->isFailed() ) &&
			( !$this->getRefresh() ) &&
			( !$this->getErrors() ) &&
			( !$this->getForm() ) &&
			( !$this->getIframe() ) &&
			( !$this->getRedirect() )
		);
	}
}
