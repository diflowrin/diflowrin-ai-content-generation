<?php
/**
 * Uninstall cleanup.
 *
 * @package Diflowrin\ContentGenerator
 */

// Only run from the WordPress uninstall process.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'diflowrin_content_generator_settings' );
