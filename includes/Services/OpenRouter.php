<?php

namespace Diflowrin\ContentGenerator\Services;

use Diflowrin\ContentGenerator\Settings\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Thin OpenRouter client (BYOK). One API key covers both text and image generation.
 *
 * Both go through POST https://openrouter.ai/api/v1/chat/completions — on
 * OpenRouter the image-capable models ARE chat models with an image output
 * modality, so a request asks for `modalities: ["image"]` and reads the result
 * back from `choices[0].message.images[]`. (The /images endpoint rejects them
 * with "model not found".)
 */
class OpenRouter {

	/**
	 * Provider root. Every other OpenRouter URL in the plugin is derived from it,
	 * so the provider is named in exactly one place.
	 */
	const SITE = 'https://openrouter.ai';

	const BASE = self::SITE . '/api/v1';

	/** Where a user creates the API key this plugin asks for. */
	const KEYS_URL = self::SITE . '/keys';

	/** Model catalogue, filtered to the models that can return an image. */
	const IMAGE_MODELS_URL = self::SITE . '/models?output_modalities=image';

	/**
	 * Display form of one of the URLs above: host and path, no scheme or query,
	 * so a link in the admin reads "openrouter.ai/keys".
	 *
	 * @param string $url One of the URL constants on this class.
	 * @return string
	 */
	public static function link_label( $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return $host . rtrim( $path, '/' );
	}

	/**
	 * @var string
	 */
	private $api_key;

	public function __construct( $api_key = null ) {
		$this->api_key = null !== $api_key ? $api_key : Settings::api_key();
	}

	/**
	 * @return bool
	 */
	public function has_key() {
		return '' !== (string) $this->api_key;
	}

	/**
	 * Shared request headers.
	 *
	 * @return array<string,string>
	 */
	private function headers() {
		return array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
			// OpenRouter attribution headers (recommended).
			'HTTP-Referer'  => home_url( '/' ),
			'X-Title'       => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Generate text via chat completions.
	 *
	 * @param string $system System prompt.
	 * @param string $user   User prompt.
	 * @param string $model  OpenRouter model slug.
	 * @return string|WP_Error Raw assistant message content.
	 */
	public function generate_text( $system, $user, $model ) {
		if ( ! $this->has_key() ) {
			return new WP_Error( 'diflowrin_content_generator_no_key', __( 'No OpenRouter API key is configured.', 'diflowrin-ai-content-generation' ) );
		}

		$response = wp_remote_post(
			self::BASE . '/chat/completions',
			array(
				'timeout' => 120,
				'headers' => $this->headers(),
				'body'    => wp_json_encode(
					array(
						'model'    => $model,
						'messages' => array(
							array(
								'role'    => 'system',
								'content' => $system,
							),
							array(
								'role'    => 'user',
								'content' => $user,
							),
						),
					)
				),
			)
		);

		$body = $this->decode( $response, 'text' );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$content = isset( $body['choices'][0]['message']['content'] ) ? $body['choices'][0]['message']['content'] : '';
		if ( '' === trim( (string) $content ) ) {
			return new WP_Error( 'diflowrin_content_generator_empty', __( 'The model returned an empty response.', 'diflowrin-ai-content-generation' ) );
		}

		return (string) $content;
	}

	/**
	 * Generate a single image with the model the user chose in Settings.
	 *
	 * @param string $prompt Image prompt.
	 * @param string $model  OpenRouter model slug with an image output modality.
	 * @return array{bytes:string,mime:string}|WP_Error
	 */
	public function generate_image( $prompt, $model ) {
		if ( ! $this->has_key() ) {
			return new WP_Error( 'diflowrin_content_generator_no_key', __( 'No OpenRouter API key is configured.', 'diflowrin-ai-content-generation' ) );
		}
		if ( '' === trim( (string) $model ) ) {
			return new WP_Error( 'diflowrin_content_generator_no_image_model', __( 'No image model is configured. Add one in Settings.', 'diflowrin-ai-content-generation' ) );
		}

		$response = wp_remote_post(
			self::BASE . '/chat/completions',
			array(
				// Image models are slower than text ones; 120s is not always enough.
				'timeout' => 180,
				'headers' => $this->headers(),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'modalities' => array( 'image' ),
					)
				),
			)
		);

		$body = $this->decode( $response, 'image' );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$source = $this->first_image_source( $body );
		if ( '' === $source ) {
			return new WP_Error(
				'diflowrin_content_generator_no_image',
				sprintf(
					/* translators: %s: OpenRouter model slug. */
					__( 'The model "%s" returned no image. Make sure the slug you set as the image model produces image output.', 'diflowrin-ai-content-generation' ),
					$model
				)
			);
		}

		// Most models inline a base64 data URI; some return a link to their CDN.
		return RemoteImage::read( $source );
	}

	/**
	 * Pull the first usable image out of a chat completion response. OpenRouter
	 * returns `message.images[].image_url.url`, but shapes vary between models,
	 * so a plain string entry is accepted too.
	 *
	 * @param array $body Decoded response body.
	 * @return string Data URI or https URL; '' when the response carries no image.
	 */
	private function first_image_source( $body ) {
		$images = isset( $body['choices'][0]['message']['images'] ) ? $body['choices'][0]['message']['images'] : array();
		if ( ! is_array( $images ) ) {
			return '';
		}

		foreach ( $images as $image ) {
			if ( is_string( $image ) && '' !== trim( $image ) ) {
				return trim( $image );
			}
			if ( is_array( $image ) && isset( $image['image_url']['url'] ) && '' !== trim( (string) $image['image_url']['url'] ) ) {
				return trim( (string) $image['image_url']['url'] );
			}
		}

		return '';
	}

	/**
	 * Validate the HTTP response and decode the JSON body, surfacing API errors.
	 *
	 * @param array|WP_Error $response wp_remote_post result.
	 * @param string         $context  'text'|'image' (for messages).
	 * @return array|WP_Error
	 */
	private function decode( $response, $context ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = '';
			if ( is_array( $json ) && isset( $json['error']['message'] ) ) {
				$message = $json['error']['message'];
			}
			if ( '' === $message ) {
				$message = sprintf(
					/* translators: %d: HTTP status code. */
					__( 'OpenRouter request failed (HTTP %d).', 'diflowrin-ai-content-generation' ),
					$code
				);
			}
			return new WP_Error( 'diflowrin_content_generator_http_' . $code, $message );
		}

		if ( ! is_array( $json ) ) {
			return new WP_Error( 'diflowrin_content_generator_bad_json', __( 'OpenRouter returned an unreadable response.', 'diflowrin-ai-content-generation' ) );
		}

		return $json;
	}
}
