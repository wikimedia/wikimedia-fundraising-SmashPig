<?php

namespace SmashPig\Core\Http;

use SmashPig\Core\ProviderConfiguration;
use SmashPig\Core\Context;

/**
 * Uses dependency injection to execute cURL requests
 */
class OutboundRequest {
	/**
	 * @var array
	 */
	protected $headers = [];

	/**
	 * @var ProviderConfiguration
	 */
	protected $config;

	/**
	 * @var string HTTP method
	 */
	protected $method;

	/**
	 * @var string Request body
	 */
	protected $body = null;

	public function __construct( $url, $method = 'GET' ) {
		$this->url = $url;
		$this->method = $method;
	}

	public function setHeader( string $name, string $value ): OutboundRequest {
		$this->headers[$name] = $value;
		return $this;
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function getUrl(): string {
		return $this->url;
	}

	public function getMethod(): string {
		return $this->method;
	}

	public function setBody( $data ): OutboundRequest {
		if ( is_array( $data ) ) {
			$this->body = http_build_query( $data );
		} else {
			$this->body = $data;
		}
		if ( $this->body === null ) {
			if ( isset( $this->headers['Content-Length'] ) ) {
				unset( $this->headers['Content-Length'] );
			}
		} else {
			$this->setHeader( 'Content-Length', strlen( $this->body ) );
		}
		return $this;
	}

	public function getBody(): string {
		return $this->body;
	}

	public function execute(): array {
		$config = Context::get()->getProviderConfiguration();
		/**
		 * @var CurlWrapper
		 */
		$wrapper = $config->object( 'curl/wrapper' );
		return $wrapper->execute(
			$this->url,
			$this->method,
			$this->getHeaders(),
			$this->body
		);
	}
}
