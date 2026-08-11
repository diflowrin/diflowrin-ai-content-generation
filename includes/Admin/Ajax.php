<?php

namespace Diflowrin\ContentGenerator\Admin;

use Diflowrin\ContentGenerator\Generator\Generator;
use Diflowrin\ContentGenerator\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * admin-ajax handler for the Generate screen.
 */
class Ajax {

	const ACTION = 'diflowrin_content_generator_generate';
	const NONCE  = 'diflowrin_content_generator_generate';

	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'generate' ) );
	}

	/**
	 * Handle a generation request.
	 */
	public function generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'diflowrin-ai-content-generation' ) ), 403 );
		}

		check_ajax_referer( self::NONCE, 'nonce' );

		$args = array(
			'topic'         => isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '',
			'tone'          => isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : '',
			'length'        => isset( $_POST['length'] ) ? sanitize_key( wp_unslash( $_POST['length'] ) ) : 'medium',
			'language'      => isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'English',
			'source_url'    => isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '',
			'with_image'    => ! empty( $_POST['with_image'] ),
			'ai_disclosure' => ! empty( $_POST['ai_disclosure'] ),
		);

		// Absent means "use the Settings default"; an explicit 0 means "none",
		// so the key is only added when the form actually sent a value.
		if ( isset( $_POST['image_count'] ) ) {
			$args['image_count'] = Settings::clamp_image_count( sanitize_text_field( wp_unslash( $_POST['image_count'] ) ) );
		}

		$result = ( new Generator() )->generate( $args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Pluralisation belongs on the server: JavaScript cannot pick the right
		// form for every locale from a single template string.
		if ( ! empty( $result['images'] ) ) {
			$result['images_message'] = sprintf(
				/* translators: %d: number of images added inside the article body. */
				_n( '%d image added inside the article.', '%d images added inside the article.', (int) $result['images'], 'diflowrin-ai-content-generation' ),
				(int) $result['images']
			);
		}

		wp_send_json_success( $result );
	}
}
