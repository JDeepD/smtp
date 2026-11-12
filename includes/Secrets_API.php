<?php
/**
 * Class WordPress\SMTP\Secrets_API
 *
 * @package smtp
 */

namespace WordPress\SMTP;

use WP_Error;

/**
 * Thin wrapper around the WordPress Secrets API (WordPress 7.2).
 *
 * Only `Credentials` uses it. Every method works on sites without the API.
 *
 * @since 1.2.0
 */
class Secrets_API {

	/**
	 * Whether secrets can be stored and read on this site.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True when the API exists, has a usable key and accepts writes.
	 */
	public function can_store(): bool {
		return function_exists( 'wp_is_secrets_api_available' )
			&& function_exists( 'wp_secrets_provider_is_writable' )
			&& wp_is_secrets_api_available()
			&& wp_secrets_provider_is_writable();
	}

	/**
	 * Whether a name is accepted by the Secrets API.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name Secret name.
	 * @return bool True when valid.
	 */
	public function is_valid_name( string $name ): bool {
		return function_exists( 'wp_secrets_validate_name' ) && true === wp_secrets_validate_name( $name );
	}

	/**
	 * Returns a secret.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name Secret name.
	 * @return string|null|WP_Error The value, null when the secret does not exist, or WP_Error when
	 *                              it exists but can not be read.
	 */
	public function get( string $name ) {
		if ( ! function_exists( 'wp_get_secret' ) ) {
			return new WP_Error( 'smtp_secrets_unavailable', __( 'The Secrets API is not available.', 'smtp' ) );
		}

		$secret = wp_get_secret( $name );

		if ( null === $secret || is_wp_error( $secret ) ) {
			return $secret;
		}

		return $secret->reveal();
	}

	/**
	 * Stores a secret.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name  Secret name.
	 * @param string $value Secret value.
	 * @return true|WP_Error
	 */
	public function set(
		string $name,
		#[\SensitiveParameter]
		string $value
	) {
		if ( ! function_exists( 'wp_set_secret' ) ) {
			return new WP_Error( 'smtp_secrets_unavailable', __( 'The Secrets API is not available.', 'smtp' ) );
		}

		return wp_set_secret( $name, $value );
	}

	/**
	 * Deletes a secret.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name Secret name.
	 * @return true|WP_Error
	 */
	public function delete( string $name ) {
		if ( ! function_exists( 'wp_delete_secret' ) ) {
			return new WP_Error( 'smtp_secrets_unavailable', __( 'The Secrets API is not available.', 'smtp' ) );
		}

		return wp_delete_secret( $name );
	}
}
