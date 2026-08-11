<?php

namespace Diflowrin\ContentGenerator\Services;

use Diflowrin\ContentGenerator\Settings\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Pexels stock photo search (BYOK).
 *
 * An alternative to generating images: instead of asking a model to invent a
 * picture, this finds a real photograph. Cheaper (the API is free) and it never
 * produces the six-fingered-hand kind of failure, but the photo is generic
 * rather than specific to the article.
 *
 * Pexels requires attribution wherever its photos appear, so every result
 * carries the photographer's name and links back; see Generator::figure_html().
 */
class Pexels {

	const BASE = 'https://api.pexels.com/v1';

	/**
	 * Pexels pages results; sampling a random early page stops every article on
	 * a site from opening with the same handful of over-used photos.
	 */
	const RANDOM_PAGES = 5;

	/**
	 * @var string
	 */
	private $api_key;

	public function __construct( $api_key = null ) {
		$this->api_key = null !== $api_key ? $api_key : Settings::pexels_api_key();
	}

	/**
	 * @return bool
	 */
	public function has_key() {
		return '' !== (string) $this->api_key;
	}

	/**
	 * Find one landscape photo for a search query and download it.
	 *
	 * @param string $query Search terms (English works best).
	 * @return array{bytes:string,mime:string,credit:array{photographer:string,photographer_url:string,url:string},alt:string}|WP_Error
	 */
	public function find_photo( $query ) {
		if ( ! $this->has_key() ) {
			return new WP_Error( 'diflowrin_content_generator_no_pexels_key', __( 'No Pexels API key is configured. Add one in Settings.', 'diflowrin-ai-content-generation' ) );
		}

		$query = trim( (string) $query );
		if ( '' === $query ) {
			return new WP_Error( 'diflowrin_content_generator_pexels_no_query', __( 'No search terms could be derived for the photo.', 'diflowrin-ai-content-generation' ) );
		}

		$photo = $this->search( $query );

		// A niche or non-English query can legitimately match nothing. Rather
		// than fail the image, fall back to Pexels' curated set so the article
		// still gets a usable photograph.
		if ( is_wp_error( $photo ) && 'diflowrin_content_generator_pexels_empty' === $photo->get_error_code() ) {
			$photo = $this->curated();
		}

		if ( is_wp_error( $photo ) ) {
			return $photo;
		}

		// large2x is a good compromise: wide enough for a featured image without
		// pulling the multi-megabyte original on every request.
		$src = '';
		foreach ( array( 'large2x', 'large', 'original' ) as $size ) {
			if ( ! empty( $photo['src'][ $size ] ) ) {
				$src = (string) $photo['src'][ $size ];
				break;
			}
		}
		if ( '' === $src ) {
			return new WP_Error( 'diflowrin_content_generator_pexels_no_src', __( 'Pexels returned a photo without a usable image file.', 'diflowrin-ai-content-generation' ) );
		}

		$image = RemoteImage::fetch( $src );
		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$image['credit'] = array(
			'photographer'     => isset( $photo['photographer'] ) ? sanitize_text_field( (string) $photo['photographer'] ) : '',
			'photographer_url' => isset( $photo['photographer_url'] ) ? esc_url_raw( (string) $photo['photographer_url'] ) : '',
			'url'              => isset( $photo['url'] ) ? esc_url_raw( (string) $photo['url'] ) : '',
		);
		// Pexels' own description of the photo makes better alt text than the
		// article heading: it describes what is actually in the picture.
		$image['alt'] = isset( $photo['alt'] ) ? sanitize_text_field( (string) $photo['alt'] ) : '';

		return $image;
	}

	/**
	 * Search for a landscape photo.
	 *
	 * @param string $query Search terms.
	 * @return array|WP_Error A single photo record.
	 */
	private function search( $query ) {
		$body = $this->get(
			'/search',
			array(
				'query'       => $query,
				'per_page'    => 1,
				'page'        => wp_rand( 1, self::RANDOM_PAGES ),
				'orientation' => 'landscape',
			)
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		// Asking for a page beyond the result set returns an empty list even
		// though the query itself matched, so retry page 1 before giving up.
		if ( empty( $body['photos'] ) ) {
			$body = $this->get(
				'/search',
				array(
					'query'       => $query,
					'per_page'    => 1,
					'page'        => 1,
					'orientation' => 'landscape',
				)
			);
			if ( is_wp_error( $body ) ) {
				return $body;
			}
		}

		if ( empty( $body['photos'][0] ) ) {
			return new WP_Error(
				'diflowrin_content_generator_pexels_empty',
				sprintf(
					/* translators: %s: search terms. */
					__( 'Pexels has no photo matching "%s".', 'diflowrin-ai-content-generation' ),
					$query
				)
			);
		}

		return $body['photos'][0];
	}

	/**
	 * A handpicked photo, used when a search matches nothing.
	 *
	 * @return array|WP_Error
	 */
	private function curated() {
		$body = $this->get(
			'/curated',
			array(
				'per_page' => 1,
				'page'     => wp_rand( 1, self::RANDOM_PAGES ),
			)
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( empty( $body['photos'][0] ) ) {
			return new WP_Error( 'diflowrin_content_generator_pexels_empty', __( 'Pexels returned no photos.', 'diflowrin-ai-content-generation' ) );
		}
		return $body['photos'][0];
	}

	/**
	 * GET a Pexels endpoint and decode the JSON body.
	 *
	 * @param string $path  Endpoint path, e.g. "/search".
	 * @param array  $args  Query arguments.
	 * @return array|WP_Error
	 */
	private function get( $path, array $args ) {
		$response = wp_remote_get(
			add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $args ) ), self::BASE . $path ),
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => $this->api_key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'diflowrin_content_generator_pexels_auth', __( 'Pexels rejected the API key. Check it in Settings.', 'diflowrin-ai-content-generation' ) );
		}
		if ( 429 === $code ) {
			return new WP_Error( 'diflowrin_content_generator_pexels_rate', __( 'Pexels rate limit reached. Try again later.', 'diflowrin-ai-content-generation' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'diflowrin_content_generator_pexels_http_' . $code,
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Pexels request failed (HTTP %d).', 'diflowrin-ai-content-generation' ),
					$code
				)
			);
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'diflowrin_content_generator_pexels_bad_json', __( 'Pexels returned an unreadable response.', 'diflowrin-ai-content-generation' ) );
		}

		// Pexels also reports failures inside the body, and a cache in front of
		// the API can hand that back with a 200. Without this the caller would
		// see "no photos found" and go hunting for better search terms when the
		// real problem is the key.
		if ( isset( $json['status'] ) && (int) $json['status'] >= 400 ) {
			$message = isset( $json['message'] ) ? (string) $json['message'] : '';
			return new WP_Error(
				'diflowrin_content_generator_pexels_error',
				'' !== $message
					/* translators: %s: error message from Pexels. */
					? sprintf( __( 'Pexels rejected the request: %s', 'diflowrin-ai-content-generation' ), $message )
					: __( 'Pexels rejected the request.', 'diflowrin-ai-content-generation' )
			);
		}

		return $json;
	}

	/**
	 * Turn an article title or section heading into search terms.
	 *
	 * Pexels matches on what is visible in a photograph, so a full headline
	 * ("How to choose the best espresso machine for a small kitchen") finds
	 * nothing useful. Filler words are dropped and the few remaining content
	 * words are kept.
	 *
	 * @param string $heading Section heading (may be empty).
	 * @param string $title   Article title, used as the fallback.
	 * @return string
	 */
	public static function build_query( $heading, $title ) {
		$query = self::keywords( $heading );
		if ( '' === $query ) {
			$query = self::keywords( $title );
		}
		return $query;
	}

	/**
	 * The content words of a phrase, at most three of them.
	 *
	 * @param string $text Phrase.
	 * @return string
	 */
	private static function keywords( $text ) {
		$stop_words = array(
			'the', 'and', 'for', 'with', 'that', 'this', 'from', 'about', 'into', 'your', 'their',
			'how', 'what', 'why', 'when', 'where', 'which', 'who', 'are', 'was', 'were', 'will',
			'can', 'does', 'has', 'have', 'been', 'best', 'top', 'most', 'more', 'very', 'just',
			'also', 'than', 'guide', 'tips', 'ways', 'things', 'step', 'steps', 'complete',
			'ultimate', 'using', 'used', 'use', 'need', 'every', 'make', 'like', 'get', 'new',
			'great', 'good', 'part', 'introduction', 'conclusion', 'overview', 'understanding',
		);

		$text = wp_strip_all_tags( (string) $text );
		// Keep letters (including accented ones) and digits; everything else is
		// punctuation that would only confuse the search.
		$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}

		$words = array();
		foreach ( preg_split( '/\s+/u', $text ) as $word ) {
			$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $word ) : strtolower( $word );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $lower ) : strlen( $lower );
			if ( $length < 3 || in_array( $lower, $stop_words, true ) ) {
				continue;
			}
			$words[ $lower ] = true; // keys de-duplicate
			if ( count( $words ) >= 3 ) {
				break;
			}
		}

		return implode( ' ', array_keys( $words ) );
	}
}
