<?php namespace SmashPig\Core\Listeners;

use SmashPig\Core\Context;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Configuration;
use SmashPig\Core\DataStores\KeyedOpaqueDataStore;
use SmashPig\Core\Messages\ListenerMessage;

abstract class ListenerBase implements IHttpActionHandler {

	/** @var Request */
	protected $request;

	/** @var Response */
	protected $response;

	/** @var Configuration object - stores all listener configuration */
	protected $c;

	/** @var KeyedOpaqueDataStore for placing messages that are in flight */
	protected $inflightStore;

	public function __construct() {
		$this->c = Context::get()->getConfiguration();
		$this->inflightStore = $this->c->obj( 'data-store/inflight' );
	}

	public function execute( Request $request, Response $response, $pathParts ) {
		$this->request = $request;
		$this->response = $response;
	}

	/**
	 * Perform security checks that do not rely on the contents of the envelope or the message.
	 *
	 * @throws ListenerSecurityException on unacceptable ingress data. Message processing should
	 * stop and the data logged.
	 */
	protected function doIngressSecurity() {
		$this->validateRemoteIp();
	}

	/**
	 * Determine remote IP address and check validity against an IP whitelist. Will throw exception
	 * on error or invalid IP.
	 *
	 * TODO: This function only handles IPv4 -- it should also handle v6
	 *
	 * @throws ListenerConfigException
	 * @throws ListenerSecurityException
	 */
	protected function validateRemoteIp() {
		// Obtain whitelist
		$whitelist = $this->c->val( 'security/ip-whitelist', true );

		// Obtain remote party IP
		$remote_ip = $this->request->getClientIp();

		// Do we continue?
		if ( empty( $whitelist ) ) {
			Logger::info( "No IP whitelist specified. Continuing and not validating remote IP '{$remote_ip}'." );
			return;
		}

		// Validate remote party IP (right now we can only handle IPv4)
		if ( !filter_var( $remote_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			throw new ListenerSecurityException( "Bizarre remote IP address: {$remote_ip}" );
		}

		// Check remote address against the IP whitelist -- the whitelist can be either individual
		// or CIDR blocks.
		foreach ( (array)$whitelist as $ip ) {
			if ( $remote_ip === $ip ) {
				return;
			} elseif ( count( explode( '/', $ip ) ) === 2 ) {
				// Obtain address, CIDR block, and verify correctness of form
				list( $network_ip, $block ) = explode( '/', $ip );
				if ( !filter_var( $network_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ||
					!filter_var( $block, FILTER_VALIDATE_INT, array( 'min_range' => 0, 'max_range' => 32 ) )
				) {
					throw new ListenerConfigException( "Malformed IP address in whitelist: {$ip}" );
				}

				// Obtain raw IP addresses
				$network_long = ip2long( $network_ip );
				$mask_long = ~( pow( 2, ( 32 - $block ) ) - 1 );
				$remote_long = ip2long( $remote_ip );

				// Validate in CIDR
				if ( ( $remote_long & $mask_long ) === ( $network_long & $mask_long ) ) {
					return; // the remote IP address is in this range
				}
			} else {
				throw new ListenerConfigException( "Malformed IP address in whitelist: {$ip}" );
			}
		}

		// we have fallen through everything in the whitelist, throw
		$agent = $this->request->server->get( 'User-Agent', '' );
		throw new ListenerSecurityException(
			"Received a connection from a bogus IP: {$remote_ip}, agent: {$agent}"
		);
	}

	/**
	 * Message object specific processing -- security/validation/actions/acknowledgement of message
	 * from envelope.
	 *
	 * This function should not throw an exception -- the intent is to allow envelope processing
	 * to continue as normal even if this Message is not normal.
	 *
	 * @param ListenerMessage $msg Message object to operate on
	 *
	 * @return bool True if the message was successfully processed. Returning false will keep the
	 * message in the pending queue.
	 */
	protected function processMessage( ListenerMessage $msg ) {
		try {
			if ( $this->doMessageSecurity( $msg ) &&
				$msg->validate() &&
				$msg->runActionChain() &&

				// Message acknowledgement must be the last thing that happens because the envelope
				// processor will use this to determine success/failure reporting status. If it was
				// the first thing we could fail later in the chain and still report a success.
				$this->ackMessage( $msg )
			) {
				return true;
			} else {
				return false;
			}
		} catch ( \Exception $ex ) {
			Logger::error( 'Failed message security check: ' . $ex->getMessage() );
		}

		// We caught exceptions: therefore the message was not correctly processed.
		return false;
	}

	/**
	 * Run any gateway/Message specific security.
	 *
	 * @param ListenerMessage $msg Message object to operate on
	 *
	 * @throws ListenerSecurityException on security violation
	 */
	abstract protected function doMessageSecurity( ListenerMessage $msg );

	/**
	 * Positive acknowledgement of successful Message processing all the way through the chain
	 *
	 * @param ListenerMessage $msg that was processed.
	 */
	abstract protected function ackMessage( ListenerMessage $msg );
}
