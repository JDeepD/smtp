<?php
/**
 * Tests for the Credentials class.
 *
 * @package smtp
 */

use WordPress\SMTP\Credentials;
use WordPress\SMTP\Test_Utils\Fake_Secrets_API;

class Credentials_Tests extends WP_UnitTestCase {

	/**
	 * @var Credentials
	 */
	private $credentials;

	/**
	 * @var Fake_Secrets_API
	 */
	private $api;

	public function set_up() {
		parent::set_up();

		$this->api            = new Fake_Secrets_API();
		$this->api->available = false;
		$this->credentials    = new Credentials( $this->api );
	}

	public function test_get_returns_an_empty_string_when_nothing_is_stored() {
		$this->assertSame( '', $this->credentials->get( 'password' ) );
	}

	public function test_set_and_get_a_secret() {
		$this->credentials->set( 'password', 'secret' );

		$this->assertSame( 'secret', $this->credentials->get( 'password' ) );
	}

	public function test_secrets_are_stored_independently() {
		$this->credentials->set( 'password', 'one' );
		$this->credentials->set( 'api_key', 'two' );

		$this->assertSame( 'one', $this->credentials->get( 'password' ) );
		$this->assertSame( 'two', $this->credentials->get( 'api_key' ) );
	}

	public function test_set_overwrites_an_existing_secret() {
		$this->credentials->set( 'password', 'old' );
		$this->credentials->set( 'password', 'new' );

		$this->assertSame( 'new', $this->credentials->get( 'password' ) );
	}

	public function test_get_ignores_a_corrupted_option() {
		update_option( Credentials::OPTION, 'not-an-array' );

		$this->assertSame( '', $this->credentials->get( 'password' ) );
	}

	public function test_set_recovers_from_a_corrupted_option() {
		update_option( Credentials::OPTION, 'not-an-array' );

		$this->credentials->set( 'password', 'secret' );

		$this->assertSame( 'secret', $this->credentials->get( 'password' ) );
	}

	public function test_secrets_are_not_autoloaded() {
		$this->credentials->set( 'password', 'secret' );

		$this->assertArrayNotHasKey( Credentials::OPTION, wp_load_alloptions( true ) );
	}

	public function test_set_stores_in_the_secrets_api_when_available() {
		$this->api->available = true;

		$this->assertTrue( $this->credentials->set( 'password', 'secret' ) );

		$this->assertSame( array( 'smtp/password' => 'secret' ), $this->api->secrets );
		$this->assertFalse( get_option( Credentials::OPTION ) );
		$this->assertSame( array( 'password' ), get_option( Credentials::SECRET_KEYS_OPTION ) );
		$this->assertSame( 'secret', $this->credentials->get( 'password' ) );
	}

	public function test_set_uses_the_option_when_the_secrets_api_is_not_available() {
		$this->assertTrue( $this->credentials->set( 'password', 'secret' ) );

		$this->assertSame( array(), $this->api->secrets );
		$this->assertSame( array( 'password' => 'secret' ), get_option( Credentials::OPTION ) );
		$this->assertFalse( get_option( Credentials::SECRET_KEYS_OPTION ) );
	}

	public function test_set_keeps_the_current_value_when_the_secrets_api_fails() {
		$this->api->available = true;
		$this->credentials->set( 'password', 'old' );
		$this->api->fail_writes = true;

		$this->assertFalse( $this->credentials->set( 'password', 'new' ) );

		$this->assertSame( 'old', $this->credentials->get( 'password' ) );
		$this->assertFalse( get_option( Credentials::OPTION ) );
	}

	public function test_set_keeps_keys_that_are_not_valid_secret_names_in_the_option() {
		$this->api->available = true;

		$this->credentials->set( 'Api Key', 'secret' );

		$this->assertSame( array(), $this->api->secrets );
		$this->assertSame( array( 'Api Key' => 'secret' ), get_option( Credentials::OPTION ) );
		$this->assertSame( 'secret', $this->credentials->get( 'Api Key' ) );
	}

	public function test_set_removes_the_plain_text_copy_when_the_secrets_api_becomes_available() {
		$this->credentials->set( 'password', 'old' );
		$this->credentials->set( 'api_key', 'kept' );
		$this->api->available = true;

		$this->credentials->set( 'password', 'new' );

		$this->assertSame( array( 'api_key' => 'kept' ), get_option( Credentials::OPTION ) );
		$this->assertSame( 'new', $this->credentials->get( 'password' ) );
	}

	public function test_a_value_entered_while_the_api_is_unavailable_wins_over_the_stored_secret() {
		$this->api->available = true;
		$this->credentials->set( 'password', 'old' );
		$this->api->available = false;

		$this->credentials->set( 'password', 'new' );

		$this->assertSame( 'new', $this->credentials->get( 'password' ) );
		$this->assertFalse( get_option( Credentials::SECRET_KEYS_OPTION ) );

		$this->api->available = true;

		$this->assertSame( 'new', $this->credentials->get( 'password' ) );
	}

	public function test_maybe_migrate_moves_the_option_to_the_secrets_api() {
		$this->credentials->set( 'password', 'secret' );
		$this->credentials->set( 'alert_slack_webhook_url', 'https://hooks.slack.com/services/secret' );
		$this->api->available = true;

		$this->credentials->maybe_migrate();
		$this->credentials->maybe_migrate();

		$this->assertSame(
			array(
				'smtp/password'                => 'secret',
				'smtp/alert_slack_webhook_url' => 'https://hooks.slack.com/services/secret',
			),
			$this->api->secrets
		);
		$this->assertFalse( get_option( Credentials::OPTION ) );
		$this->assertSame( 'secret', $this->credentials->get( 'password' ) );
		$this->assertSame( 'https://hooks.slack.com/services/secret', $this->credentials->get( 'alert_slack_webhook_url' ) );
	}

	public function test_maybe_migrate_does_nothing_when_the_secrets_api_is_not_available() {
		$this->credentials->set( 'password', 'secret' );

		$this->credentials->maybe_migrate();

		$this->assertSame( array( 'password' => 'secret' ), get_option( Credentials::OPTION ) );
	}

	public function test_maybe_migrate_keeps_the_option_when_the_secret_can_not_be_read_back() {
		$this->credentials->set( 'password', 'secret' );
		$this->api->available  = true;
		$this->api->unreadable = array( 'smtp/password' );

		$this->credentials->maybe_migrate();

		$this->assertSame( array( 'password' => 'secret' ), get_option( Credentials::OPTION ) );
		$this->assertFalse( get_option( Credentials::SECRET_KEYS_OPTION ) );
		$this->assertSame( 'secret', $this->credentials->get( 'password' ) );
	}

	public function test_maybe_migrate_keeps_the_option_when_the_secrets_api_fails() {
		$this->credentials->set( 'password', 'secret' );
		$this->api->available   = true;
		$this->api->fail_writes = true;

		$this->credentials->maybe_migrate();

		$this->assertSame( array( 'password' => 'secret' ), get_option( Credentials::OPTION ) );
	}

	public function test_an_unreadable_secret_is_never_read_from_the_option() {
		$this->api->available = true;
		$this->credentials->set( 'password', 'secret' );
		$this->api->unreadable = array( 'smtp/password' );

		$this->assertSame( '', $this->credentials->get( 'password' ) );
		$this->assertSame( array( 'password' ), $this->credentials->get_unreadable_keys() );
	}

	public function test_secrets_can_not_be_read_once_the_secrets_api_is_gone() {
		$this->api->available = true;
		$this->credentials->set( 'password', 'secret' );
		$this->api->available = false;

		$this->assertSame( '', $this->credentials->get( 'password' ) );
		$this->assertSame( array( 'password' ), $this->credentials->get_unreadable_keys() );
	}

	public function test_get_unreadable_keys_is_empty_when_every_secret_can_be_read() {
		$this->credentials->set( 'api_key', 'in-the-option' );
		$this->api->available = true;
		$this->credentials->set( 'password', 'secret' );

		$this->assertSame( array(), $this->credentials->get_unreadable_keys() );
	}

	public function test_get_storage() {
		$this->assertSame( Credentials::STORAGE_OPTION, $this->credentials->get_storage() );

		$this->api->available = true;

		$this->assertSame( Credentials::STORAGE_SECRETS, $this->credentials->get_storage() );
	}

	public function test_the_list_of_secret_keys_is_autoloaded() {
		$this->api->available = true;
		$this->credentials->set( 'password', 'secret' );

		$this->assertArrayHasKey( Credentials::SECRET_KEYS_OPTION, wp_load_alloptions( true ) );
	}

	public function test_add_hooks_migrates_on_admin_init() {
		$this->credentials->add_hooks();

		$this->assertSame( 10, has_action( 'admin_init', array( $this->credentials, 'maybe_migrate' ) ) );
	}
}
