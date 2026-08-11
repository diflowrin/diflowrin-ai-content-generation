<?php

namespace Diflowrin\ContentGenerator;

use Diflowrin\ContentGenerator\Admin\Admin;
use Diflowrin\ContentGenerator\Rest\SeoMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin container. Wires up the pieces on the right hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var SeoMeta
	 */
	private $seo_meta;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Translations load automatically from translate.wordpress.org (WP 4.6+).
		add_action( 'init', array( $this, 'register_protocol' ) );

		// REST plumbing — must load on front-end/REST requests too, not just admin.
		$this->seo_meta = new SeoMeta();

		if ( is_admin() ) {
			new Admin();
		}
	}

	/**
	 * Allow the desktop app's custom scheme through WordPress URL sanitisation,
	 * so the Application Passwords authorize redirect back to the app survives esc_url().
	 */
	public function register_protocol() {
		add_filter(
			'kses_allowed_protocols',
			static function ( $protocols ) {
				$protocols[] = DIFLOWRIN_CG_DEEPLINK_SCHEME;
				return $protocols;
			}
		);
	}
}
