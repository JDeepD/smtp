<?php
/**
 * Tests for the Secrets_API class.
 *
 * @package smtp
 */

use WordPress\SMTP\Credentials;
use WordPress\SMTP\Secrets_API;

class Secrets_API_Tests extends WP_UnitTestCase {

	/**
	 * @var Secrets_API
	 */
	private $api;

	public function set_up() {
		parent::set_up();

		$this->api = new Secrets_API();
	}

	private function require_secrets_api() {
		if ( ! function_exists( 'wp_is_secrets_api_available' ) || ! wp_is_secrets_api_available() ) {
			$this->markTestSkipped( 'The Secrets API is not available.' );
		}
	}

	public function test_every_method_fails_safely_without_the_secrets_api() {
		if ( function_exists( 'wp_get_secret' ) ) {
			$this->markTestSkipped( 'The Secrets API is available.' );
		}

		$this->assertFalse( $this->api->can_store() );
		$this->assertFalse( $this->api->is_valid_name( 'smtp/password' ) );
		$this->assertWPError( $this->api->get( 'smtp/password' ) );
		$this->assertWPError( $this->api->set( 'smtp/password', 'secret' ) );
		$this->assertWPError( $this->api->delete( 'smtp/password' ) );
	}

	public function test_set_get_and_delete() {
		$this->require_secrets_api();

		$this->assertTrue( $this->api->can_store() );
		$this->assertNull( $this->api->get( 'smtp/password' ) );

		$this->assertTrue( $this->api->set( 'smtp/password', 'secret' ) );
		$this->assertSame( 'secret', $this->api->get( 'smtp/password' ) );

		$this->assertTrue( $this->api->delete( 'smtp/password' ) );
		$this->assertNull( $this->api->get( 'smtp/password' ) );
	}

	public function test_is_valid_name() {
		$this->require_secrets_api();

		$this->assertTrue( $this->api->is_valid_name( 'smtp/password' ) );
		$this->assertTrue( $this->api->is_valid_name( 'smtp/alert_slack_webhook_url' ) );
		$this->assertFalse( $this->api->is_valid_name( 'smtp/Api Key' ) );
		$this->assertFalse( $this->api->is_valid_name( 'smtp/_password' ) );
	}

	public function test_credentials_store_secrets_encrypted() {
		$this->require_secrets_api();

		( new Credentials() )->set( 'password', 'top-secret-value' );

		$this->assertSame( 'top-secret-value', wp_get_secret( 'smtp/password' )->reveal() );
		$this->assertFalse( get_option( Credentials::OPTION ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertStringNotContainsString( 'top-secret-value', implode( "\n", $GLOBALS['wpdb']->get_col( "SELECT option_value FROM {$GLOBALS['wpdb']->options}" ) ) );
	}
}
