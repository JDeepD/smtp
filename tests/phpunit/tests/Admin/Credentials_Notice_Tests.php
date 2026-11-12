<?php
/**
 * Tests for the Credentials_Notice class.
 *
 * @package smtp
 */

use WordPress\SMTP\Admin\Credentials_Notice;
use WordPress\SMTP\Credentials;
use WordPress\SMTP\Test_Utils\Fake_Secrets_API;

class Credentials_Notice_Tests extends WP_UnitTestCase {

	/**
	 * @var Fake_Secrets_API
	 */
	private $api;

	/**
	 * @var Credentials
	 */
	private $credentials;

	/**
	 * @var Credentials_Notice
	 */
	private $notice;

	public function set_up() {
		parent::set_up();

		$this->api         = new Fake_Secrets_API();
		$this->credentials = new Credentials( $this->api );
		$this->notice      = new Credentials_Notice( $this->credentials );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function render(): string {
		ob_start();
		$this->notice->render();

		return (string) ob_get_clean();
	}

	public function test_nothing_is_shown_when_every_secret_can_be_read() {
		$this->credentials->set( 'password', 'secret' );

		$this->assertSame( '', $this->render() );
	}

	public function test_nothing_is_shown_for_secrets_stored_in_the_option() {
		$this->api->available = false;
		$this->credentials->set( 'password', 'secret' );

		$this->assertSame( '', $this->render() );
	}

	public function test_the_notice_is_shown_when_a_secret_can_not_be_read() {
		$this->credentials->set( 'password', 'top-secret-value' );
		$this->api->unreadable = array( 'smtp/password' );

		$html = $this->render();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( esc_url( admin_url( 'site-health.php' ) ), $html );
		$this->assertStringContainsString( 'tab=settings', $html );
		$this->assertStringNotContainsString( 'top-secret-value', $html );
	}

	public function test_the_notice_is_shown_when_the_secrets_api_is_gone() {
		$this->credentials->set( 'password', 'secret' );
		$this->api->available = false;

		$this->assertStringContainsString( 'notice-error', $this->render() );
	}

	public function test_the_notice_links_to_the_alerts_tab_when_only_alert_secrets_are_unreadable() {
		$this->credentials->set( 'alert_slack_webhook_url', 'https://hooks.slack.com/services/secret' );
		$this->api->unreadable = array( 'smtp/alert_slack_webhook_url' );

		$html = $this->render();

		$this->assertStringContainsString( 'tab=alerts', $html );
		$this->assertStringNotContainsString( 'tab=settings', $html );
	}

	public function test_the_notice_goes_away_once_the_password_is_entered_again() {
		$this->credentials->set( 'password', 'secret' );
		$this->api->available = false;

		$this->credentials->set( 'password', 'entered-again' );

		$this->assertSame( '', $this->render() );
	}

	public function test_nothing_is_shown_without_the_capability() {
		$this->credentials->set( 'password', 'secret' );
		$this->api->unreadable = array( 'smtp/password' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( '', $this->render() );
	}
}
