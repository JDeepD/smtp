<?php
/**
 * Class WordPress\SMTP\Credentials
 *
 * @package smtp
 */

namespace WordPress\SMTP;

/**
 * Reads and writes secret values (passwords, API keys).
 *
 * All access to secrets goes through this class. Secrets are stored with the WordPress Secrets API
 * when it is available, and in the `smtp_credentials` option otherwise. The `smtp_secret_keys`
 * option lists the keys stored with the Secrets API, so each key is read from one place only.
 *
 * @since 1.2.0
 */
class Credentials {

	/**
	 * Name of the option that stores the secrets when the Secrets API is not used.
	 *
	 * @since 1.2.0
	 */
	const OPTION = 'smtp_credentials';

	/**
	 * Name of the option that lists the keys stored with the Secrets API.
	 *
	 * @since 1.2.0
	 */
	const SECRET_KEYS_OPTION = 'smtp_secret_keys';

	/**
	 * Namespace of the secret names.
	 *
	 * @since 1.2.0
	 */
	const SECRET_NAMESPACE = 'smtp';

	/**
	 * Storage reported when secrets go to the Secrets API.
	 *
	 * @since 1.2.0
	 */
	const STORAGE_SECRETS = 'secrets';

	/**
	 * Storage reported when secrets go to the option.
	 *
	 * @since 1.2.0
	 */
	const STORAGE_OPTION = 'option';

	/**
	 * Secrets API wrapper.
	 *
	 * @since 1.2.0
	 * @var Secrets_API
	 */
	private $secrets;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Secrets_API|null $secrets Optional Secrets API wrapper.
	 */
	public function __construct( ?Secrets_API $secrets = null ) {
		$this->secrets = $secrets ? $secrets : new Secrets_API();
	}

	/**
	 * Registers the hooks.
	 *
	 * @since 1.2.0
	 */
	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_migrate' ) );
	}

	/**
	 * Returns a stored secret.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Secret key.
	 * @return string Secret value, or an empty string when not set or when it can not be read.
	 */
	public function get( string $key ): string {
		if ( in_array( $key, $this->get_secret_keys(), true ) ) {
			$value = $this->secrets->get( $this->get_secret_name( $key ) );

			return is_string( $value ) ? $value : '';
		}

		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || ! isset( $stored[ $key ] ) || ! is_string( $stored[ $key ] ) ) {
			return '';
		}

		return $stored[ $key ];
	}

	/**
	 * Stores a secret.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key   Secret key.
	 * @param string $value Secret value.
	 * @return bool False when the Secrets API failed to store it. The current value is kept.
	 */
	public function set(
		string $key,
		#[\SensitiveParameter]
		string $value
	): bool {
		$name = $this->get_secret_name( $key );

		if ( $this->secrets->can_store() && $this->secrets->is_valid_name( $name ) ) {
			if ( is_wp_error( $this->secrets->set( $name, $value ) ) ) {
				return false;
			}

			$this->add_secret_key( $key );
			$this->delete_from_option( $key );

			return true;
		}

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$stored[ $key ] = $value;

		// Not autoloaded: secrets are only needed when sending mail or saving settings.
		update_option( self::OPTION, $stored, false );

		if ( in_array( $key, $this->get_secret_keys(), true ) ) {
			$this->remove_secret_key( $key );
			$this->secrets->delete( $name );
		}

		return true;
	}

	/**
	 * Moves the secrets stored in the option to the Secrets API, once it is available.
	 *
	 * Each value is read back before it is removed from the option. Safe to run more than once.
	 *
	 * @since 1.2.0
	 */
	public function maybe_migrate() {
		if ( ! $this->secrets->can_store() ) {
			return;
		}

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || array() === $stored ) {
			return;
		}

		foreach ( $stored as $key => $value ) {
			$key  = (string) $key;
			$name = $this->get_secret_name( $key );

			if ( ! is_string( $value ) || '' === $value || ! $this->secrets->is_valid_name( $name ) ) {
				continue;
			}

			if ( is_wp_error( $this->secrets->set( $name, $value ) ) || $this->secrets->get( $name ) !== $value ) {
				continue;
			}

			$this->add_secret_key( $key );
			$this->delete_from_option( $key );
		}
	}

	/**
	 * Returns the keys stored with the Secrets API that can not be read.
	 *
	 * This happens when the Secrets API was removed, or when its key changed or was lost.
	 *
	 * @since 1.2.0
	 *
	 * @return string[] Secret keys.
	 */
	public function get_unreadable_keys(): array {
		$unreadable = array();

		foreach ( $this->get_secret_keys() as $key ) {
			if ( ! is_string( $this->secrets->get( $this->get_secret_name( $key ) ) ) ) {
				$unreadable[] = $key;
			}
		}

		return $unreadable;
	}

	/**
	 * Returns where new secrets are stored.
	 *
	 * @since 1.2.0
	 *
	 * @return string `secrets` for the Secrets API, `option` otherwise.
	 */
	public function get_storage(): string {
		return $this->secrets->can_store() ? self::STORAGE_SECRETS : self::STORAGE_OPTION;
	}

	/**
	 * Returns the Secrets API name of a key.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Secret key.
	 * @return string Secret name.
	 */
	private function get_secret_name( string $key ): string {
		return self::SECRET_NAMESPACE . '/' . $key;
	}

	/**
	 * Returns the keys stored with the Secrets API.
	 *
	 * @since 1.2.0
	 *
	 * @return string[] Secret keys.
	 */
	private function get_secret_keys(): array {
		$keys = get_option( self::SECRET_KEYS_OPTION, array() );

		return is_array( $keys ) ? array_values( array_filter( $keys, 'is_string' ) ) : array();
	}

	/**
	 * Records that a key is stored with the Secrets API.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Secret key.
	 */
	private function add_secret_key( string $key ) {
		$keys = $this->get_secret_keys();

		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_option( self::SECRET_KEYS_OPTION, $keys, true );
		}
	}

	/**
	 * Records that a key is no longer stored with the Secrets API.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Secret key.
	 */
	private function remove_secret_key( string $key ) {
		$keys = array_values( array_diff( $this->get_secret_keys(), array( $key ) ) );

		if ( array() === $keys ) {
			delete_option( self::SECRET_KEYS_OPTION );
		} else {
			update_option( self::SECRET_KEYS_OPTION, $keys, true );
		}
	}

	/**
	 * Removes a key from the option.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Secret key.
	 */
	private function delete_from_option( string $key ) {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || ! array_key_exists( $key, $stored ) ) {
			return;
		}

		unset( $stored[ $key ] );

		if ( array() === $stored ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $stored, false );
		}
	}
}
