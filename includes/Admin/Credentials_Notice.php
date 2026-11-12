<?php
/**
 * Class WordPress\SMTP\Admin\Credentials_Notice
 *
 * @package smtp
 */

namespace WordPress\SMTP\Admin;

use WordPress\SMTP\Credentials;

/**
 * Tells administrators when secrets stored with the WordPress Secrets API can not be read.
 *
 * The plugin then works as if those secrets were never entered, and never falls back to plain text.
 *
 * @since 1.2.0
 */
class Credentials_Notice {

	/**
	 * Credentials storage.
	 *
	 * @since 1.2.0
	 * @var Credentials
	 */
	private $credentials;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Credentials $credentials Credentials storage.
	 */
	public function __construct( Credentials $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Registers the hooks.
	 *
	 * @since 1.2.0
	 */
	public function add_hooks() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Shows the notice when a stored secret can not be read.
	 *
	 * @since 1.2.0
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$keys = $this->credentials->get_unreadable_keys();

		if ( array() === $keys ) {
			return;
		}

		$alerts_only = array() === array_filter(
			$keys,
			static function ( $key ) {
				return 0 !== strpos( $key, 'alert_' );
			}
		);

		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: 1: URL of the Site Health screen, 2: URL of the SMTP settings page */
						__( 'SMTP can not read the passwords it saved with the WordPress Secrets API, so it works as if they were never entered. <a href="%1$s">Check the Secrets API in Site Health</a>, or <a href="%2$s">enter them again</a>.', 'smtp' ),
						esc_url( admin_url( 'site-health.php' ) ),
						esc_url( Admin_Page::get_url( $alerts_only ? 'alerts' : 'settings' ) )
					),
					array( 'a' => array( 'href' => array() ) )
				);
				?>
			</p>
		</div>
		<?php
	}
}
