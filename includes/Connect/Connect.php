<?php

namespace Diflowrin\ContentGenerator\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * One-click connection to the SEO Content Architect desktop app.
 *
 * Uses WordPress core's built-in Application Passwords authorization flow
 * (wp-admin/authorize-application.php). The user approves, WordPress mints an
 * Application Password and redirects to the app's custom scheme with the
 * credentials. No passwords are generated or stored by this plugin.
 */
class Connect {

	const APP_NAME          = 'SEO Content Architect Desktop';
	const DISCONNECT_ACTION = 'diflowrin_content_generator_disconnect';

	public function __construct() {
		add_action( 'admin_post_' . self::DISCONNECT_ACTION, array( $this, 'handle_disconnect' ) );
	}

	/**
	 * Details of the current user's connection to the desktop app, if any.
	 *
	 * WordPress core stores the Application Password at approval time, so its
	 * presence confirms the connection was authorized; last_used flips once the
	 * app actually authenticates against the REST API.
	 *
	 * @return array{created:int,last_used:int,last_ip:string}|null Newest matching connection, or null.
	 */
	public function connection_info() {
		if ( ! class_exists( '\WP_Application_Passwords' ) ) {
			return null;
		}

		$newest = null;
		foreach ( \WP_Application_Passwords::get_user_application_passwords( get_current_user_id() ) as $item ) {
			if ( ! isset( $item['name'] ) || self::APP_NAME !== $item['name'] ) {
				continue;
			}
			if ( null === $newest || (int) $item['created'] > (int) $newest['created'] ) {
				$newest = $item;
			}
		}

		if ( null === $newest ) {
			return null;
		}

		return array(
			'created'   => (int) $newest['created'],
			'last_used' => isset( $newest['last_used'] ) ? (int) $newest['last_used'] : 0,
			'last_ip'   => isset( $newest['last_ip'] ) ? (string) $newest['last_ip'] : '',
		);
	}

	/**
	 * URL that revokes every "SEO Content Architect Desktop" Application Password
	 * for the current user.
	 *
	 * @return string
	 */
	public function disconnect_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::DISCONNECT_ACTION ),
			self::DISCONNECT_ACTION
		);
	}

	/**
	 * admin-post handler: revoke the app's Application Passwords and return
	 * to the Connect screen.
	 */
	public function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'diflowrin-ai-content-generation' ) );
		}
		check_admin_referer( self::DISCONNECT_ACTION );

		$user_id = get_current_user_id();
		if ( class_exists( '\WP_Application_Passwords' ) ) {
			foreach ( \WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
				if ( isset( $item['name'], $item['uuid'] ) && self::APP_NAME === $item['name'] ) {
					\WP_Application_Passwords::delete_application_password( $user_id, $item['uuid'] );
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . DIFLOWRIN_CG_SLUG . '&diflowrin_content_generator_disconnected=1' ) );
		exit;
	}

	/**
	 * Build the Application Passwords authorize URL that hands off to the app.
	 *
	 * @return string
	 */
	public function authorize_url() {
		// http_build_query() URL-encodes values (add_query_arg() does not), so the
		// spaces in app_name and the custom scheme in the callback survive intact.
		$args = array(
			'app_name'    => self::APP_NAME,
			'success_url' => DIFLOWRIN_CG_DEEPLINK_SCHEME . '://connect',
			'reject_url'  => DIFLOWRIN_CG_DEEPLINK_SCHEME . '://connect?rejected=1',
		);

		return admin_url( 'authorize-application.php' ) . '?' . http_build_query( $args );
	}

	/**
	 * Whether the environment can complete the connection.
	 *
	 * @return bool
	 */
	public function is_ready() {
		foreach ( $this->preflight() as $check ) {
			if ( 'error' === $check['status'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Environment checks shown on the Connect screen.
	 *
	 * @return array<int,array{label:string,status:string,detail:string}>
	 */
	public function preflight() {
		$checks = array();

		// 1. HTTPS — Application Passwords require a secure connection.
		$checks[] = array(
			'label'  => __( 'Secure connection (HTTPS)', 'diflowrin-ai-content-generation' ),
			'status' => is_ssl() ? 'ok' : 'error',
			'detail' => is_ssl()
				? __( 'Your site is served over HTTPS.', 'diflowrin-ai-content-generation' )
				: __( 'Application Passwords require HTTPS. Enable SSL on your site to connect.', 'diflowrin-ai-content-generation' ),
		);

		// 2. Application Passwords feature availability.
		$app_pw = function_exists( 'wp_is_application_passwords_available' )
			? wp_is_application_passwords_available()
			: false;
		$checks[] = array(
			'label'  => __( 'Application Passwords', 'diflowrin-ai-content-generation' ),
			'status' => $app_pw ? 'ok' : 'error',
			'detail' => $app_pw
				? __( 'Application Passwords are enabled on this site.', 'diflowrin-ai-content-generation' )
				: __( 'Application Passwords are not available. They require WordPress 5.6+ over HTTPS and must not be disabled by a security plugin.', 'diflowrin-ai-content-generation' ),
		);

		// 3. REST API base (informational — the app talks to WordPress over REST).
		$checks[] = array(
			'label'  => __( 'REST API', 'diflowrin-ai-content-generation' ),
			'status' => 'info',
			'detail' => sprintf(
				/* translators: %s: REST API base URL. */
				__( 'The app connects via the WordPress REST API at %s', 'diflowrin-ai-content-generation' ),
				esc_url_raw( rest_url() )
			),
		);

		return $checks;
	}

	/**
	 * Product page for the desktop app (what it is, what it costs).
	 *
	 * @return string
	 */
	public function app_website() {
		return DIFLOWRIN_CG_APP_WEBSITE;
	}

	/**
	 * Store listing the app is installed from.
	 *
	 * @return string
	 */
	public function app_store_url() {
		return DIFLOWRIN_CG_APP_STORE;
	}
}
