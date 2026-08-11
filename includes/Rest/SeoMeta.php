<?php

namespace Diflowrin\ContentGenerator\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the meta title / meta description of the major SEO plugins over the
 * REST API, so the SEO Content Architect desktop app can set them when it
 * publishes a post.
 *
 * WordPress hides meta keys starting with "_" from the REST API, which is why
 * these grouped fields exist at all. Authentication is left entirely to
 * WordPress core (Application Passwords); nothing here inspects credentials.
 */
class SeoMeta {

	const NS        = 'diflowrin-cg/v1';
	const NS_LEGACY = 'seo-architect/v1';

	/**
	 * REST field name => array( title meta key, description meta key ).
	 *
	 * RankMath is the odd one out: its keys have no underscore prefix.
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private $fields = array(
		'yoast_meta'    => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ),
		'siteseo_meta'  => array( '_siteseo_titles_title', '_siteseo_titles_desc' ),
		'seopress_meta' => array( '_seopress_titles_title', '_seopress_titles_desc' ),
		'rankmath_meta' => array( 'rank_math_title', 'rank_math_description' ),
	);

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register the grouped fields and the batch endpoint.
	 */
	public function register() {
		// The standalone "SEO Architect Helper" plugin registers the same fields
		// and the same legacy route. When it is active it owns them — registering
		// twice would just overwrite its callbacks with identical ones.
		if ( function_exists( 'seo_architect_update_meta' ) ) {
			return;
		}

		foreach ( $this->fields as $field => $keys ) {
			register_rest_field(
				'post',
				$field,
				array(
					'get_callback'    => function ( $post ) use ( $keys ) {
						return array(
							'title'       => (string) get_post_meta( (int) $post['id'], $keys[0], true ),
							'description' => (string) get_post_meta( (int) $post['id'], $keys[1], true ),
						);
					},
					'update_callback' => function ( $value, $post ) use ( $keys ) {
						return $this->write_meta( $value, $post, $keys );
					},
					'schema'          => array(
						'description' => __( 'SEO meta title and description.', 'diflowrin-ai-content-generation' ),
						'type'        => 'object',
						'properties'  => array(
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
						),
						// Read back only in edit context: these are protected meta
						// keys, and the app reads them from the create/update
						// response, which core always prepares in edit context.
						'context'     => array( 'edit' ),
					),
				)
			);
		}

		$route = array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_meta' ),
			'permission_callback' => array( $this, 'can_edit_requested_post' ),
			'args'                => $this->endpoint_args(),
		);

		register_rest_route( self::NS, '/update-meta', $route );

		// Compatibility route for desktop app builds that only know the helper
		// plugin's namespace. Safe to register: we already stood down above if
		// the helper itself is active.
		register_rest_route( self::NS_LEGACY, '/update-meta', $route );
	}

	/**
	 * Write one plugin's meta pair from a grouped REST field.
	 *
	 * @param mixed    $value Field value submitted by the client.
	 * @param \WP_Post $post  Post being updated.
	 * @param array    $keys  array( title meta key, description meta key ).
	 * @return true|\WP_Error
	 */
	private function write_meta( $value, $post, array $keys ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'diflowrin_cg_invalid_seo_meta',
				__( 'SEO meta must be an object with a title and/or description.', 'diflowrin-ai-content-generation' ),
				array( 'status' => 400 )
			);
		}

		// Core already checks edit_post before calling update_callback; this is
		// defence in depth, and keeps the check next to the write.
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error(
				'diflowrin_cg_cannot_edit_seo_meta',
				__( 'You are not allowed to edit the SEO meta of this post.', 'diflowrin-ai-content-generation' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( isset( $value['title'] ) ) {
			update_post_meta( $post->ID, $keys[0], sanitize_text_field( $value['title'] ) );
		}
		if ( isset( $value['description'] ) ) {
			update_post_meta( $post->ID, $keys[1], sanitize_textarea_field( $value['description'] ) );
		}

		return true;
	}

	/**
	 * Permission check for /update-meta. Runs before the callback, so the
	 * per-post capability is enforced even if the callback ever changes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function can_edit_requested_post( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( $post_id <= 0 ) {
			return new \WP_Error(
				'diflowrin_cg_missing_post_id',
				__( 'A valid post ID is required.', 'diflowrin-ai-content-generation' ),
				array( 'status' => 400 )
			);
		}

		if ( ! get_post( $post_id ) ) {
			return new \WP_Error(
				'diflowrin_cg_post_not_found',
				__( 'Post not found.', 'diflowrin-ai-content-generation' ),
				array( 'status' => 404 )
			);
		}

		// edit_posts alone is not enough: it would let any contributor rewrite
		// the SEO meta of somebody else's post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'diflowrin_cg_cannot_edit_seo_meta',
				__( 'You are not allowed to edit the SEO meta of this post.', 'diflowrin-ai-content-generation' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Batch endpoint: set every supported SEO plugin's meta in one request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	public function update_meta( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$updated = array();

		foreach ( $this->fields as $field => $keys ) {
			$prefix = str_replace( '_meta', '', $field );

			$title = $request->get_param( $prefix . '_title' );
			if ( null !== $title ) {
				update_post_meta( $post_id, $keys[0], sanitize_text_field( $title ) );
				$updated[] = $prefix . '_title';
			}

			$desc = $request->get_param( $prefix . '_desc' );
			if ( null !== $desc ) {
				update_post_meta( $post_id, $keys[1], sanitize_textarea_field( $desc ) );
				$updated[] = $prefix . '_desc';
			}
		}

		return array(
			'success' => true,
			'message' => __( 'SEO meta fields updated.', 'diflowrin-ai-content-generation' ),
			'post_id' => $post_id,
			'updated' => $updated,
		);
	}

	/**
	 * Argument schema for /update-meta.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function endpoint_args() {
		$args = array(
			'post_id' => array(
				'type'              => 'integer',
				'required'          => true,
				'description'       => __( 'ID of the post to update.', 'diflowrin-ai-content-generation' ),
				'sanitize_callback' => 'absint',
			),
		);

		foreach ( array_keys( $this->fields ) as $field ) {
			$prefix = str_replace( '_meta', '', $field );

			$args[ $prefix . '_title' ] = array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			);
			$args[ $prefix . '_desc' ] = array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			);
		}

		return $args;
	}
}
