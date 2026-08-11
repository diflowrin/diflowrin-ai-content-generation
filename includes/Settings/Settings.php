<?php

namespace Diflowrin\ContentGenerator\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage (single option array) + WordPress Settings API registration.
 *
 * Keys:
 *   - openrouter_api_key : string (BYOK — encrypted at rest, see encrypt_secret())
 *   - pexels_api_key     : string (BYOK — encrypted at rest; only for the Pexels image source)
 *   - model              : string (OpenRouter model slug used for generation)
 *   - image_source       : string (openrouter|pexels — where article images come from)
 *   - image_model        : string (OpenRouter model slug used for every generated image)
 *   - image_count        : int    (0-4, default number of in-article images)
 *   - image_style        : string (optional look applied to every image prompt)
 *   - image_text_allowed : bool   (let the model render text inside images)
 *   - ai_disclosure_on   : bool   (whether the Generate screen's disclosure box starts ticked)
 *   - ai_disclosure_text : string (notice appended when the Generate screen asks for it)
 *   - sonar_enabled      : bool   (run a Perplexity Sonar research pass before writing)
 *   - sonar_model        : string (OpenRouter Sonar model slug for the research pass)
 *
 * Security posture for the API key:
 *   - Encrypted at rest (sodium secretbox, key derived from the wp-config salts),
 *     so a database-only leak (dump/SQLi/backup) does not reveal it — an attacker
 *     would also need the wp-config.php salts from the filesystem.
 *   - The option is stored with autoload=no, keeping the key out of
 *     wp_load_alloptions() and object-cache dumps.
 *   - If the salts are ever rotated, decryption fails safely: the key reads as
 *     empty and the user simply re-enters it in Settings.
 */
class Settings {

	const OPTION              = 'diflowrin_content_generator_settings';
	const GROUP               = 'diflowrin_content_generator_settings_group';
	const DEFAULT_MODEL       = 'openai/gpt-4o-mini';
	const DEFAULT_IMAGE_MODEL = 'google/gemini-2.5-flash-image';
	const DEFAULT_SONAR_MODEL = 'perplexity/sonar';

	/**
	 * In-article images: how many are offered by default, and the ceiling.
	 * Each one is a separate paid request, so the cap keeps a single generation
	 * from quietly running up a bill.
	 */
	const DEFAULT_IMAGE_COUNT = 2;
	const MAX_IMAGE_COUNT     = 4;

	/**
	 * Marker prefix for values encrypted by encrypt_secret() (vs legacy plaintext).
	 */
	const CIPHER_PREFIX = 'ca1:';

	/**
	 * Where article images come from.
	 */
	const SOURCE_OPENROUTER = 'openrouter';
	const SOURCE_PEXELS     = 'pexels';

	/**
	 * Option keys holding a secret. Every one of them is encrypted at rest,
	 * preserved when its form field is submitted blank, and migrated from
	 * legacy plaintext — listing them here is what keeps those three behaviours
	 * in step when a key is added.
	 *
	 * @var string[]
	 */
	const SECRET_KEYS = array( 'openrouter_api_key', 'pexels_api_key' );

	/**
	 * Register the option with the Settings API.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		// Keep the option out of alloptions so the API key never rides along in
		// every request's option cache (or object-cache dumps).
		// Re-creating the option is the only autoload flip that works on every
		// supported WP version (wp_set_option_autoload() needs 6.4+).
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, array(), '', 'no' );
		} elseif ( isset( wp_load_alloptions()[ self::OPTION ] ) ) {
			$value = get_option( self::OPTION );
			delete_option( self::OPTION );
			add_option( self::OPTION, $value, '', 'no' );
		}

		// One-time migration: encrypt any legacy plaintext key at rest.
		$opts    = self::all();
		$changed = false;
		foreach ( self::SECRET_KEYS as $key ) {
			$stored = isset( $opts[ $key ] ) ? (string) $opts[ $key ] : '';
			if ( '' !== $stored && 0 !== strpos( $stored, self::CIPHER_PREFIX ) && function_exists( 'sodium_crypto_secretbox' ) ) {
				$opts[ $key ] = self::encrypt_secret( $stored );
				$changed      = true;
			}
		}
		if ( $changed ) {
			update_option( self::OPTION, $opts );
		}
	}

	// -----------------------------------------------------------------
	// Secret encryption (sodium secretbox, keyed from the wp-config salts)
	// -----------------------------------------------------------------

	/**
	 * 32-byte symmetric key derived from the site's auth salt. Lives in
	 * wp-config.php, so DB access alone is not enough to decrypt.
	 *
	 * @return string
	 */
	private static function crypto_key() {
		return sodium_crypto_generichash( wp_salt( 'auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Encrypt a secret for storage. Returns the input unchanged when sodium is
	 * unavailable (WordPress bundles sodium_compat, so this is a rare edge).
	 *
	 * @param string $plaintext Secret value.
	 * @return string
	 */
	public static function encrypt_secret( $plaintext ) {
		if ( '' === $plaintext || ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return $plaintext;
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, self::crypto_key() );
		return self::CIPHER_PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe storage of ciphertext, not obfuscation.
	}

	/**
	 * Decrypt a stored secret. Legacy plaintext values pass through unchanged;
	 * undecryptable values (rotated salts, corrupt data) return ''.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt_secret( $stored ) {
		if ( '' === $stored ) {
			return '';
		}
		if ( 0 !== strpos( $stored, self::CIPHER_PREFIX ) ) {
			return $stored; // Legacy plaintext (migrated on next register()/save).
		}
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}

		// Defensive loop: heals values that were double-encrypted before
		// sanitize() became idempotent. Normally runs exactly once.
		$value = $stored;
		for ( $i = 0; $i < 3 && 0 === strpos( $value, self::CIPHER_PREFIX ); $i++ ) {
			$raw = base64_decode( substr( $value, strlen( self::CIPHER_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}
			$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, self::crypto_key() );
			if ( false === $plain ) {
				return '';
			}
			$value = $plain;
		}
		return 0 === strpos( $value, self::CIPHER_PREFIX ) ? '' : $value;
	}

	/**
	 * Work out what to store for one secret field.
	 *
	 * Three cases, and getting any of them wrong silently destroys a key the
	 * user already saved:
	 *   - submitted blank  -> keep what is stored (the form never renders the
	 *                         real value, so blank means "unchanged", not "clear")
	 *   - already ciphertext -> store as-is; update_option() runs this sanitizer
	 *                         on every programmatic write, and re-encrypting
	 *                         would nest ciphertexts
	 *   - new plaintext    -> encrypt it
	 *
	 * @param array  $input    Raw submitted values.
	 * @param array  $existing Currently stored settings.
	 * @param string $key      Option key.
	 * @return string
	 */
	private static function sanitize_secret( array $input, array $existing, $key ) {
		$submitted = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';

		if ( '' === $submitted ) {
			$kept = isset( $existing[ $key ] ) ? (string) $existing[ $key ] : '';
			return ( '' !== $kept && 0 !== strpos( $kept, self::CIPHER_PREFIX ) )
				? self::encrypt_secret( $kept ) // predates encrypted storage
				: $kept;
		}

		if ( 0 === strpos( $submitted, self::CIPHER_PREFIX ) ) {
			return $submitted;
		}

		return self::encrypt_secret( sanitize_text_field( $submitted ) );
	}

	/**
	 * Sanitise + preserve the stored API keys when their fields are submitted blank.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$existing = self::all();
		$clean    = array();

		foreach ( self::SECRET_KEYS as $secret_key ) {
			$clean[ $secret_key ] = self::sanitize_secret( $input, $existing, $secret_key );
		}

		$model          = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '';
		$clean['model'] = '' !== $model ? $model : self::DEFAULT_MODEL;

		// Whitelist: an unknown value falls back to AI generation rather than
		// silently disabling images.
		$source                = isset( $input['image_source'] ) ? sanitize_key( $input['image_source'] ) : '';
		$clean['image_source'] = self::SOURCE_PEXELS === $source ? self::SOURCE_PEXELS : self::SOURCE_OPENROUTER;

		$image_model          = isset( $input['image_model'] ) ? sanitize_text_field( $input['image_model'] ) : '';
		$clean['image_model'] = '' !== $image_model ? $image_model : self::DEFAULT_IMAGE_MODEL;

		// 0 is a meaningful value here ("no in-article images"), so an absent
		// field falls back to the default while an explicit 0 is kept.
		$clean['image_count'] = isset( $input['image_count'] )
			? self::clamp_image_count( $input['image_count'] )
			: self::DEFAULT_IMAGE_COUNT;

		$clean['image_style'] = isset( $input['image_style'] ) ? sanitize_text_field( $input['image_style'] ) : '';

		// Checkbox: absent when unchecked, so its presence is the value.
		$clean['ai_disclosure_on'] = ! empty( $input['ai_disclosure_on'] ) ? 1 : 0;

		// Plain text only: it is wrapped in the plugin's own markup, so letting
		// HTML in here would only be a way to break the article layout.
		$disclosure                  = isset( $input['ai_disclosure_text'] ) ? sanitize_text_field( $input['ai_disclosure_text'] ) : '';
		$clean['ai_disclosure_text'] = '' !== $disclosure ? $disclosure : self::default_disclosure();

		// Checkbox: absent when unchecked, so its presence is the value.
		$clean['image_text_allowed'] = ! empty( $input['image_text_allowed'] ) ? 1 : 0;

		// Checkbox: absent when unchecked, so its presence is the value.
		$clean['sonar_enabled'] = ! empty( $input['sonar_enabled'] ) ? 1 : 0;

		$sonar_model          = isset( $input['sonar_model'] ) ? sanitize_text_field( $input['sonar_model'] ) : '';
		$clean['sonar_model'] = '' !== $sonar_model ? $sonar_model : self::DEFAULT_SONAR_MODEL;

		return $clean;
	}

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public static function all() {
		$opts = get_option( self::OPTION, array() );
		return is_array( $opts ) ? $opts : array();
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$opts = self::all();
		return isset( $opts[ $key ] ) && '' !== $opts[ $key ] ? $opts[ $key ] : $default;
	}

	/**
	 * The OpenRouter API key, decrypted and ready to use.
	 *
	 * @return string Empty string when unset or undecryptable (rotated salts).
	 */
	public static function api_key() {
		return self::decrypt_secret( (string) self::get( 'openrouter_api_key', '' ) );
	}

	/**
	 * Whether a usable OpenRouter key is stored.
	 *
	 * @return bool
	 */
	public static function has_api_key() {
		return '' !== self::api_key();
	}

	/**
	 * Force a submitted image count into the supported 0..MAX range.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function clamp_image_count( $value ) {
		return max( 0, min( self::MAX_IMAGE_COUNT, (int) $value ) );
	}

	/**
	 * The Pexels API key, decrypted and ready to use.
	 *
	 * @return string Empty string when unset or undecryptable (rotated salts).
	 */
	public static function pexels_api_key() {
		return self::decrypt_secret( (string) self::get( 'pexels_api_key', '' ) );
	}

	/**
	 * Whether a usable Pexels key is stored.
	 *
	 * @return bool
	 */
	public static function has_pexels_key() {
		return '' !== self::pexels_api_key();
	}

	/**
	 * Where article images come from: SOURCE_OPENROUTER or SOURCE_PEXELS.
	 *
	 * @return string
	 */
	public static function image_source() {
		return self::SOURCE_PEXELS === self::get( 'image_source', self::SOURCE_OPENROUTER )
			? self::SOURCE_PEXELS
			: self::SOURCE_OPENROUTER;
	}

	/**
	 * Whether images come from Pexels rather than an AI model.
	 *
	 * @return bool
	 */
	public static function uses_pexels() {
		return self::SOURCE_PEXELS === self::image_source();
	}

	/**
	 * The OpenRouter model slug used for every generated image.
	 *
	 * @return string
	 */
	public static function image_model() {
		return (string) self::get( 'image_model', self::DEFAULT_IMAGE_MODEL );
	}

	/**
	 * How many in-article images a generation gets by default.
	 *
	 * @return int
	 */
	public static function image_count() {
		return self::clamp_image_count( self::get( 'image_count', self::DEFAULT_IMAGE_COUNT ) );
	}

	/**
	 * Optional look appended to every image prompt (e.g. "flat vector illustration").
	 *
	 * @return string
	 */
	public static function image_style() {
		return trim( (string) self::get( 'image_style', '' ) );
	}

	/**
	 * Default AI-disclosure wording.
	 *
	 * Deliberately claims only what is true of every draft: that AI produced it.
	 * It does not say the text was fact-checked or reviewed, because at the
	 * moment it is written nobody has read it yet.
	 *
	 * Not a class constant: it goes through the translation functions, which
	 * cannot run at constant-definition time.
	 *
	 * @return string
	 */
	public static function default_disclosure() {
		return __( 'This article was produced with the help of artificial intelligence.', 'diflowrin-ai-content-generation' );
	}

	/**
	 * Whether the disclosure box on the Generate screen starts ticked.
	 *
	 * This is a DEFAULT, not a master switch — same relationship as the
	 * in-article image count: Settings decides where the control starts, and
	 * the Generate screen still decides per article. Off by default, so a
	 * notice is never added to an article without someone choosing it.
	 *
	 * @return bool
	 */
	public static function disclosure_enabled() {
		return (bool) self::get( 'ai_disclosure_on', 0 );
	}

	/**
	 * The notice appended to an article when the Generate screen asks for it.
	 *
	 * @return string
	 */
	public static function disclosure_text() {
		$text = trim( (string) self::get( 'ai_disclosure_text', '' ) );
		return '' !== $text ? $text : self::default_disclosure();
	}

	/**
	 * Whether the image model may render text inside images.
	 *
	 * Off by default: most image models turn text into misspelled gibberish, and
	 * a wrong word baked into a picture cannot be translated, read by a screen
	 * reader, or fixed without regenerating the image.
	 *
	 * @return bool
	 */
	public static function image_text_allowed() {
		return (bool) self::get( 'image_text_allowed', 0 );
	}

	/**
	 * Whether the Sonar research pass is enabled (defaults to on until the
	 * settings form has been saved at least once with the box unchecked).
	 *
	 * @return bool
	 */
	public static function sonar_enabled() {
		return (bool) self::get( 'sonar_enabled', 1 );
	}
}
