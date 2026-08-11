<?php
/**
 * Settings screen.
 *
 * @var \Diflowrin\ContentGenerator\Settings\Settings $settings
 * @package Diflowrin\ContentGenerator
 */

use Diflowrin\ContentGenerator\Settings\Settings;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template is require()d inside an Admin method, so these variables are function-scoped, not global.
defined( 'ABSPATH' ) || exit;

$has_key       = Settings::has_api_key();
$model         = Settings::get( 'model', Settings::DEFAULT_MODEL );
$image_model   = Settings::image_model();
$image_count   = Settings::image_count();
$image_style   = Settings::image_style();
$image_text    = Settings::image_text_allowed();
$image_source  = Settings::image_source();
$has_pexels    = Settings::has_pexels_key();
$disclosure    = Settings::disclosure_text();
$disclosure_on = Settings::disclosure_enabled();
$sonar_enabled = Settings::sonar_enabled();
$sonar_model   = Settings::get( 'sonar_model', Settings::DEFAULT_SONAR_MODEL );
?>
<div class="wrap ca-wrap">

	<header class="ca-header">
		<h1 class="ca-header__title"><?php esc_html_e( 'Diflowrin AI Content Generation settings', 'diflowrin-ai-content-generation' ); ?></h1>
		<p class="ca-header__sub">
			<?php esc_html_e( 'Bring your own OpenRouter key. One key gives access to Claude, Gemini, GPT and more.', 'diflowrin-ai-content-generation' ); ?>
		</p>
	</header>

	<form method="post" action="options.php" class="ca-card ca-form">
		<?php settings_fields( Settings::GROUP ); ?>

		<div class="ca-field">
			<label class="ca-label" for="ca-openrouter-key"><?php esc_html_e( 'OpenRouter API key', 'diflowrin-ai-content-generation' ); ?></label>
			<div class="ca-input-group">
				<input
					type="password"
					id="ca-openrouter-key"
					class="ca-input"
					name="<?php echo esc_attr( Settings::OPTION ); ?>[openrouter_api_key]"
					value=""
					autocomplete="off"
					spellcheck="false"
					placeholder="<?php echo $has_key ? esc_attr__( 'A key is saved — leave blank to keep it', 'diflowrin-ai-content-generation' ) : 'sk-or-...'; ?>"
				/>
				<button
					type="button"
					class="ca-btn ca-btn--ghost ca-btn--small"
					data-ca-toggle="ca-openrouter-key"
					data-show-label="<?php esc_attr_e( 'Show', 'diflowrin-ai-content-generation' ); ?>"
					data-hide-label="<?php esc_attr_e( 'Hide', 'diflowrin-ai-content-generation' ); ?>"
				>
					<?php esc_html_e( 'Show', 'diflowrin-ai-content-generation' ); ?>
				</button>
			</div>
			<p class="ca-help">
				<?php
				printf(
					/* translators: %s: link to openrouter.ai/keys */
					esc_html__( 'Create a key at %s. Stored encrypted in your site database and used only for generation requests you trigger.', 'diflowrin-ai-content-generation' ),
					'<a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">openrouter.ai/keys</a>'
				);
				?>
			</p>
			<?php if ( $has_key ) : ?>
				<p class="ca-note ca-note--ok"><?php esc_html_e( 'A key is currently saved.', 'diflowrin-ai-content-generation' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ca-field">
			<label class="ca-label" for="ca-model"><?php esc_html_e( 'Default model', 'diflowrin-ai-content-generation' ); ?></label>
			<input
				type="text"
				id="ca-model"
				class="ca-input"
				name="<?php echo esc_attr( Settings::OPTION ); ?>[model]"
				value="<?php echo esc_attr( $model ); ?>"
				spellcheck="false"
			/>
			<p class="ca-help">
				<?php esc_html_e( 'OpenRouter model slug used for article generation, e.g. openai/gpt-4o-mini or anthropic/claude-sonnet-4.', 'diflowrin-ai-content-generation' ); ?>
			</p>
		</div>

		<div class="ca-field">
			<label class="ca-label" for="ca-image-source"><?php esc_html_e( 'Where images come from', 'diflowrin-ai-content-generation' ); ?></label>
			<select
				id="ca-image-source"
				class="ca-input"
				name="<?php echo esc_attr( Settings::OPTION ); ?>[image_source]"
			>
				<option value="<?php echo esc_attr( Settings::SOURCE_OPENROUTER ); ?>"<?php selected( Settings::SOURCE_OPENROUTER, $image_source ); ?>>
					<?php esc_html_e( 'AI generation (OpenRouter)', 'diflowrin-ai-content-generation' ); ?>
				</option>
				<option value="<?php echo esc_attr( Settings::SOURCE_PEXELS ); ?>"<?php selected( Settings::SOURCE_PEXELS, $image_source ); ?>>
					<?php esc_html_e( 'Pexels stock photos', 'diflowrin-ai-content-generation' ); ?>
				</option>
			</select>
			<p class="ca-help">
				<?php esc_html_e( 'AI generation invents a picture from your article and costs one paid request per image. Pexels finds a real photograph instead: the API is free, nothing is invented, but the photo is generic rather than specific to your text. Both fill the featured image and the in-article slots.', 'diflowrin-ai-content-generation' ); ?>
			</p>
			<?php if ( Settings::SOURCE_PEXELS === $image_source && ! $has_pexels ) : ?>
				<p class="ca-note ca-note--warn">
					<?php esc_html_e( 'Pexels is selected but no Pexels API key is saved yet — add one below, or images will fail.', 'diflowrin-ai-content-generation' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="ca-field">
			<label class="ca-label" for="ca-pexels-key"><?php esc_html_e( 'Pexels API key', 'diflowrin-ai-content-generation' ); ?></label>
			<div class="ca-input-group">
				<input
					type="password"
					id="ca-pexels-key"
					class="ca-input"
					name="<?php echo esc_attr( Settings::OPTION ); ?>[pexels_api_key]"
					value=""
					autocomplete="off"
					spellcheck="false"
					placeholder="<?php echo $has_pexels ? esc_attr__( 'A key is saved — leave blank to keep it', 'diflowrin-ai-content-generation' ) : esc_attr__( 'Only needed for Pexels stock photos', 'diflowrin-ai-content-generation' ); ?>"
				/>
				<button
					type="button"
					class="ca-btn ca-btn--ghost ca-btn--small"
					data-ca-toggle="ca-pexels-key"
					data-show-label="<?php esc_attr_e( 'Show', 'diflowrin-ai-content-generation' ); ?>"
					data-hide-label="<?php esc_attr_e( 'Hide', 'diflowrin-ai-content-generation' ); ?>"
				>
					<?php esc_html_e( 'Show', 'diflowrin-ai-content-generation' ); ?>
				</button>
			</div>
			<p class="ca-help">
				<?php
				printf(
					/* translators: %s: link to pexels.com/api */
					esc_html__( 'Free key from %s. Stored encrypted, exactly like your OpenRouter key. Every photo is credited to its photographer with a link back to Pexels, as their API terms require.', 'diflowrin-ai-content-generation' ),
					'<a href="https://www.pexels.com/api/" target="_blank" rel="noopener noreferrer">pexels.com/api</a>'
				);
				?>
			</p>
			<?php if ( $has_pexels ) : ?>
				<p class="ca-note ca-note--ok"><?php esc_html_e( 'A Pexels key is currently saved.', 'diflowrin-ai-content-generation' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ca-field">
			<label class="ca-label" for="ca-image-model"><?php esc_html_e( 'Image model (AI generation only)', 'diflowrin-ai-content-generation' ); ?></label>
			<input
				type="text"
				id="ca-image-model"
				class="ca-input"
				name="<?php echo esc_attr( Settings::OPTION ); ?>[image_model]"
				value="<?php echo esc_attr( $image_model ); ?>"
				spellcheck="false"
			/>
			<p class="ca-help">
				<?php
				printf(
					/* translators: 1: example model slug, 2: link to the OpenRouter model list. */
					esc_html__( 'OpenRouter model used for the featured image and for in-article illustrations, e.g. %1$s. Pick any model with image output from %2$s.', 'diflowrin-ai-content-generation' ),
					'<code>' . esc_html( Settings::DEFAULT_IMAGE_MODEL ) . '</code>',
					'<a href="https://openrouter.ai/models?output_modalities=image" target="_blank" rel="noopener noreferrer">openrouter.ai/models</a>'
				);
				?>
			</p>
		</div>

		<div class="ca-field">
			<label class="ca-label" for="ca-image-count"><?php esc_html_e( 'Images inside the article', 'diflowrin-ai-content-generation' ); ?></label>
			<select
				id="ca-image-count"
				class="ca-input"
				name="<?php echo esc_attr( Settings::OPTION ); ?>[image_count]"
			>
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
				<?php esc_html_e( 'How many illustrations a new article gets by default, spread one per section and uploaded to your media library. You can change this per article on the Generate screen. Each image is a separate paid request to your OpenRouter account.', 'diflowrin-ai-content-generation' ); ?>
			</p>
		</div>

		<div class="ca-field">
			<label class="ca-label" for="ca-image-style"><?php esc_html_e( 'Image style (optional, AI generation only)', 'diflowrin-ai-content-generation' ); ?></label>
			<input
				type="text"
				id="ca-image-style"
				class="ca-input"
				name="<?php echo esc_attr( Settings::OPTION ); ?>[image_style]"
				value="<?php echo esc_attr( $image_style ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. flat vector illustration, muted colours', 'diflowrin-ai-content-generation' ); ?>"
			/>
			<p class="ca-help">
				<?php esc_html_e( 'Added to every image prompt so your visuals stay consistent. Leave empty for the model\'s default look.', 'diflowrin-ai-content-generation' ); ?>
			</p>
		</div>

		<div class="ca-field">
			<label class="ca-check">
				<input
					type="checkbox"
					id="ca-image-text-allowed"
					name="<?php echo esc_attr( Settings::OPTION ); ?>[image_text_allowed]"
					value="1"
					<?php checked( $image_text ); ?>
				/>
				<span><?php esc_html_e( 'Allow text inside generated images (AI generation only)', 'diflowrin-ai-content-generation' ); ?></span>
			</label>
			<p class="ca-help">
				<?php esc_html_e( 'Off by default: every image prompt tells the model to produce no text at all. Turn this on and that instruction is dropped, so the model may write words, labels or captions into the picture.', 'diflowrin-ai-content-generation' ); ?>
			</p>
			<p class="ca-note ca-note--warn">
				<?php esc_html_e( 'Only enable this with an image model that handles typography well, such as Google\'s Nano Banana (Gemini image models) or an OpenAI image model. Most other models render text as misspelled gibberish, and a wrong word baked into an image cannot be translated, read out by a screen reader, or corrected without generating the image again.', 'diflowrin-ai-content-generation' ); ?>
			</p>
		</div>

		<div class="ca-field">
			<label class="ca-check">
				<input
					type="checkbox"
					id="ca-ai-disclosure-on"
					name="<?php echo esc_attr( Settings::OPTION ); ?>[ai_disclosure_on]"
					value="1"
					<?php checked( $disclosure_on ); ?>
				/>
				<span><?php esc_html_e( 'Add the AI disclosure notice by default', 'diflowrin-ai-content-generation' ); ?></span>
			</label>
			<p class="ca-help">
				<?php esc_html_e( 'Ticked here, the box on the Generate screen starts ticked too, so every new article gets the notice unless you untick it for that one. Unticked, the notice is only added when you ask for it per article.', 'diflowrin-ai-content-generation' ); ?>
			</p>
		</div>

		<div class="ca-field">
			<label class="ca-label" for="ca-ai-disclosure-text"><?php esc_html_e( 'AI disclosure notice', 'diflowrin-ai-content-generation' ); ?></label>
			<input
				type="text"
				id="ca-ai-disclosure-text"
				class="ca-input"
				name="<?php echo esc_attr( Settings::OPTION ); ?>[ai_disclosure_text]"
				value="<?php echo esc_attr( $disclosure ); ?>"
			/>
			<p class="ca-help">
				<?php esc_html_e( 'The sentence added as the last paragraph of the article. Write it in the language you publish in: it is inserted exactly as saved.', 'diflowrin-ai-content-generation' ); ?>
			</p>
		</div>

		<div class="ca-field">
			<label class="ca-check">
				<input
					type="checkbox"
					id="ca-sonar-enabled"
					name="<?php echo esc_attr( Settings::OPTION ); ?>[sonar_enabled]"
					value="1"
					<?php checked( $sonar_enabled ); ?>
				/>
				<span><?php esc_html_e( 'Sonar web research (recommended)', 'diflowrin-ai-content-generation' ); ?></span>
			</label>
			<p class="ca-help">
				<?php esc_html_e( 'Before writing, Perplexity Sonar researches your topic on the live web — or reads your source URL — and the findings ground the article in current facts. Uses the same OpenRouter key (one extra, inexpensive request per article).', 'diflowrin-ai-content-generation' ); ?>
			</p>
		</div>

		<div class="ca-field">
			<label class="ca-label" for="ca-sonar-model"><?php esc_html_e( 'Sonar model', 'diflowrin-ai-content-generation' ); ?></label>
			<input
				type="text"
				id="ca-sonar-model"
				class="ca-input"
				name="<?php echo esc_attr( Settings::OPTION ); ?>[sonar_model]"
				value="<?php echo esc_attr( $sonar_model ); ?>"
				spellcheck="false"
			/>
			<p class="ca-help">
				<?php esc_html_e( 'OpenRouter Sonar model slug used for the research pass: perplexity/sonar (fast) or perplexity/sonar-pro (deeper research).', 'diflowrin-ai-content-generation' ); ?>
			</p>
		</div>

		<div class="ca-form__actions">
			<button type="submit" class="ca-btn ca-btn--primary"><?php esc_html_e( 'Save settings', 'diflowrin-ai-content-generation' ); ?></button>
		</div>
	</form>
</div>
