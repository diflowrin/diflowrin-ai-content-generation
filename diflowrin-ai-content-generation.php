<?php
/**
 * Plugin Name:       Diflowrin AI Content Generation
 * Description:       Write SEO-ready articles with AI (BYOK via OpenRouter) and connect your site to the SEO Content Architect desktop app for bulk generation, auto-posting and social distribution.
 * Version:           1.2.1
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Diflowrin
 * Author URI:        https://diflowrin.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       diflowrin-ai-content-generation
 *
 * @package Diflowrin\ContentGenerator
 */

defined( 'ABSPATH' ) || exit; // No direct access.

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'DIFLOWRIN_CG_VERSION', '1.2.1' );
define( 'DIFLOWRIN_CG_FILE', __FILE__ );
define( 'DIFLOWRIN_CG_PATH', plugin_dir_path( __FILE__ ) );
define( 'DIFLOWRIN_CG_URL', plugin_dir_url( __FILE__ ) );
define( 'DIFLOWRIN_CG_BASENAME', plugin_basename( __FILE__ ) );
define( 'DIFLOWRIN_CG_SLUG', 'diflowrin-ai-content-generation' );

// Custom URL scheme the desktop app registers to receive the connection handoff.
// NOTE: the desktop app must register this protocol (setAsDefaultProtocolClient).
define( 'DIFLOWRIN_CG_DEEPLINK_SCHEME', 'contentarchitect' );

// Product page for the desktop app ("learn more" / "get the app").
define( 'DIFLOWRIN_CG_APP_WEBSITE', 'https://diflowrin.com/seo-content-architect/' );

// Where the app is actually installed from. Kept separate from the product page:
// "install it" and "read about it" are different intents, and only one of them
// should send the user to a store listing.
define( 'DIFLOWRIN_CG_APP_STORE', 'https://apps.microsoft.com/store/detail/9NL3GZLPH01Z?cid=DevShareMCLPCS' );

// ---------------------------------------------------------------------------
// PSR-4-ish autoloader (no composer/build step required).
// Diflowrin\ContentGenerator\Foo\Bar  ->  includes/Foo/Bar.php
// ---------------------------------------------------------------------------
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Diflowrin\ContentGenerator\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = DIFLOWRIN_CG_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
/**
 * Main plugin instance.
 *
 * @return \Diflowrin\ContentGenerator\Plugin
 */
function diflowrin_content_generator() {
	return \Diflowrin\ContentGenerator\Plugin::instance();
}

diflowrin_content_generator();
