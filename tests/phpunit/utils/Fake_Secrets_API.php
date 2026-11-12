<?php
/**
 * Class WordPress\SMTP\Test_Utils\Fake_Secrets_API
 *
 * @package smtp
 */

namespace WordPress\SMTP\Test_Utils;

use WordPress\SMTP\Secrets_API;
use WP_Error;

/**
 * In-memory stand-in for the WordPress Secrets API.
 */
class Fake_Secrets_API extends Secrets_API {

	/**
	 * @var string[]
	 */
	public $secrets = array();

	/**
	 * @var bool
	 */
	public $available = true;

	/**
	 * @var bool
	 */
	public $fail_writes = false;

	/**
	 * @var string[]
	 */
	public $unreadable = array();

	public function can_store(): bool {
		return $this->available;
	}

	public function is_valid_name( string $name ): bool {
		return 1 === preg_match( '#^[a-z0-9]([a-z0-9_-]*[a-z0-9])?/[a-z0-9]([a-z0-9_-]*[a-z0-9])?\z#', $name );
	}

	public function get( string $name ) {
		if ( ! $this->available || in_array( $name, $this->unreadable, true ) ) {
			return new WP_Error( 'secret_unreadable', 'Unreadable.' );
		}

		return $this->secrets[ $name ] ?? null;
	}

	public function set( string $name, string $value ) {
		if ( ! $this->available || $this->fail_writes ) {
			return new WP_Error( 'secret_write_failed', 'Write failed.' );
		}

		$this->secrets[ $name ] = $value;

		return true;
	}

	public function delete( string $name ) {
		if ( ! $this->available ) {
			return new WP_Error( 'secret_unavailable', 'Unavailable.' );
		}

		unset( $this->secrets[ $name ] );

		return true;
	}
}
