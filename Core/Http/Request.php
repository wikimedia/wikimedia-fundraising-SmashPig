<?php namespace SmashPig\Core\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request extends SymfonyRequest {
	public function getRawRequest() {
		return file_get_contents( 'php://input' );
	}

	public function getValues() {
		return $this->query->all() +
			$this->attributes->all() +
			$this->request->all();
	}
}
