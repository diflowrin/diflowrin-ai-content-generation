<?php
/**
 * Generate screen.
 *
 * @package Diflowrin\ContentGenerator
 */

use Diflowrin\ContentGenerator\Settings\Settings;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template is require()d inside an Admin method, so these variables are function-scoped, not global.
defined( 'ABSPATH' ) || exit;

$has_key       = Settings::has_api_key();
$sonar_enabled = Settings::sonar_enabled();
$image_count   = Settings::image_count();
$disclosure_on = Settings::disclosure_enabled();
$settings_url  = admin_url( 'admin.php?page=' . DIFLOWRIN_CG_SLUG . '-settings' );
?>
<div class="wrap ca-wrap">

	<header class="ca-header">
		<h1 class="ca-header__title"><?php esc_html_e( 'Generate an article', 'diflowrin-ai-content-generation' ); ?></h1>
		<p class="ca-header__sub">
			<?php esc_html_e( 'Turn a topic into a publish-ready draft with AI. It lands in your drafts for review before publishing.', 'diflowrin-ai-content-generation' ); ?>
		</p>
	</header>

	<?php if ( ! $has_key ) : ?>
		<div class="ca-card ca-card--soft">
			<p class="ca-note ca-note--warn" style="margin:0;">
				<?php
				printf(
					/* translators: %s: settings page link. */
					esc_html__( 'Add your OpenRouter API key in %s before generating.', 'diflowrin-ai-content-generation' ),
					'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'diflowrin-ai-content-generation' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="ca-grid">
		<section class="ca-card">
			<form id="ca-generate-form" class="ca-form" autocomplete="off">

				<div class="ca-field">
					<label class="ca-label" for="ca-topic"><?php esc_html_e( 'Topic or keyword', 'diflowrin-ai-content-generation' ); ?></label>
					<input type="text" id="ca-topic" class="ca-input" placeholder="<?php esc_attr_e( 'e.g. Best espresso machines for small kitchens', 'diflowrin-ai-content-generation' ); ?>" />
					<p class="ca-help">
						<?php esc_html_e( 'Optional when a source URL is provided — the article is then written about that page.', 'diflowrin-ai-content-generation' ); ?>
					</p>
				</div>

				<div class="ca-field">
					<label class="ca-label" for="ca-source-url"><?php esc_html_e( 'Source URL (optional)', 'diflowrin-ai-content-generation' ); ?></label>
					<input type="url" id="ca-source-url" class="ca-input" placeholder="https://example.com/article-to-base-on" />
					<p class="ca-help">
						<?php esc_html_e( 'Paste a link and the article will be based on that page\'s content, rewritten in original words. A link alone is enough — no topic needed.', 'diflowrin-ai-content-generation' ); ?>
					</p>
				</div>

				<?php if ( $sonar_enabled ) : ?>
					<p class="ca-note ca-note--ok">
						<?php esc_html_e( 'Sonar web research is on: each generation is grounded in live research on your topic or source link. Manage it in Settings.', 'diflowrin-ai-content-generation' ); ?>
					</p>
				<?php endif; ?>

				<div class="ca-field-row">
					<div class="ca-field">
						<label class="ca-label" for="ca-tone"><?php esc_html_e( 'Tone', 'diflowrin-ai-content-generation' ); ?></label>
						<select id="ca-tone" class="ca-input">
							<option value="professional"><?php esc_html_e( 'Professional', 'diflowrin-ai-content-generation' ); ?></option>
							<option value="conversational"><?php esc_html_e( 'Conversational', 'diflowrin-ai-content-generation' ); ?></option>
							<option value="friendly"><?php esc_html_e( 'Friendly', 'diflowrin-ai-content-generation' ); ?></option>
							<option value="authoritative"><?php esc_html_e( 'Authoritative', 'diflowrin-ai-content-generation' ); ?></option>
						</select>
					</div>

					<div class="ca-field">
						<label class="ca-label" for="ca-length"><?php esc_html_e( 'Length', 'diflowrin-ai-content-generation' ); ?></label>
						<select id="ca-length" class="ca-input">
							<option value="short"><?php esc_html_e( 'Short (~1000 words)', 'diflowrin-ai-content-generation' ); ?></option>
							<option value="medium" selected><?php esc_html_e( 'Medium (~2000 words)', 'diflowrin-ai-content-generation' ); ?></option>
							<option value="long"><?php esc_html_e( 'Long (~3000 words)', 'diflowrin-ai-content-generation' ); ?></option>
						</select>
					</div>

					<div class="ca-field">
						<label class="ca-label" for="ca-language"><?php esc_html_e( 'Language', 'diflowrin-ai-content-generation' ); ?></label>
						<select id="ca-language" class="ca-input">
							<?php foreach ( \Diflowrin\ContentGenerator\Generator\Generator::LANGUAGES as $language_name ) : ?>
								<option value="<?php echo esc_attr( $language_name ); ?>"<?php selected( 'English', $language_name ); ?>>
									<?php echo esc_html( $language_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="ca-field">
					<label class="ca-check">
						<input type="checkbox" id="ca-with-image" />
						<span><?php esc_html_e( 'Also generate a featured image', 'diflowrin-ai-content-generation' ); ?></span>
					</label>
				</div>

				<div class="ca-field">
					<label class="ca-label" for="ca-image-count"><?php esc_html_e( 'Images inside the article', 'diflowrin-ai-content-generation' ); ?></label>
					<select id="ca-image-count" class="ca-input">
						<option value="0"<?php selected( 0, $image_count ); ?>><?php esc_html_e( 'None', 'diflowrin-ai-content-generation' ); ?></option>
						<?php for ( $option = 1; $option <= Settings::MAX_IMAGE_COUNT; $option++ ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>"<?php selected( $option, $image_count ); ?>>
								<?php
								printf(
									/* translators: %d: number of images. */
									esc_html( _n( '%d image', '%d images', $option, 'diflowrin-ai-content-generation' ) ),
									(int) $option
								);
								?>
							</option>
						<?php endfor; ?>
					</select>
					<p class="ca-help">
						<?php esc_html_e( 'Illustrations are generated with your image model and placed one per section. Each image is a separate paid request to your OpenRouter account. Change the default in Settings.', 'diflowrin-ai-content-generation' ); ?>
					</p>
				</div>

				<div class="ca-field">
					<label class="ca-check">
						<input type="checkbox" id="ca-ai-disclosure"<?php checked( $disclosure_on ); ?> />
						<span><?php esc_html_e( 'Tell readers the article was written with AI', 'diflowrin-ai-content-generation' ); ?></span>
					</label>
					<p class="ca-help">
						<?php
						printf(
							/* translators: %s: the disclosure sentence that will be added. */
							esc_html__( 'Adds a short notice at the very end of the article: %s Edit the wording in Settings.', 'diflowrin-ai-content-generation' ),
							'<em>' . esc_html( Settings::disclosure_text() ) . '</em>'
						);
						?>
					</p>
				</div>

				<div class="ca-form__actions">
					<button type="submit" id="ca-generate-btn" class="ca-btn ca-btn--primary" <?php disabled( ! $has_key ); ?>>
						<?php esc_html_e( 'Generate draft', 'diflowrin-ai-content-generation' ); ?>
					</button>
				</div>
			</form>
		</section>

		<aside class="ca-card ca-card--soft" aria-live="polite">
			<h2 class="ca-card__title"><?php esc_html_e( 'Result', 'diflowrin-ai-content-generation' ); ?></h2>
			<div id="ca-result" class="ca-result">
				<p class="ca-muted" style="margin:0;"><?php esc_html_e( 'Your generated draft will appear here.', 'diflowrin-ai-content-generation' ); ?></p>
			</div>
		</aside>
	</div>
</div>
