<?php

namespace Diflowrin\ContentGenerator\Generator;

use Diflowrin\ContentGenerator\Services\OpenRouter;
use Diflowrin\ContentGenerator\Services\Pexels;
use Diflowrin\ContentGenerator\Services\RemoteImage;
use Diflowrin\ContentGenerator\Settings\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a topic into a draft post: prompt -> OpenRouter -> parse -> wp_insert_post,
 * with optional AI-generated images (a featured cover and/or illustrations woven
 * into the body, one per section).
 */
class Generator {

	/**
	 * Approximate target word counts per length option.
	 */
	const LENGTHS = array(
		'short'  => 1000,
		'medium' => 2000,
		'long'   => 3000,
	);

	/**
	 * Supported article languages (same list as the SEO Content Architect desktop app).
	 * Also acts as a whitelist: anything else falls back to English.
	 */
	const LANGUAGES = array(
		'English', 'Spanish', 'French', 'German', 'Chinese (Simplified)', 'Chinese (Traditional)',
		'Japanese', 'Korean', 'Portuguese', 'Italian', 'Russian', 'Arabic', 'Hindi', 'Bengali',
		'Turkish', 'Vietnamese', 'Polish', 'Dutch', 'Thai', 'Romanian', 'Greek', 'Czech',
		'Swedish', 'Hungarian', 'Danish', 'Finnish', 'Norwegian', 'Slovak', 'Indonesian',
		'Malay', 'Hebrew', 'Persian', 'Ukrainian', 'Bulgarian', 'Serbian', 'Croatian',
		'Lithuanian', 'Latvian', 'Estonian', 'Slovenian', 'Filipino', 'Swahili', 'Amharic',
		'Zulu', 'Afrikaans', 'Marathi', 'Telugu', 'Tamil', 'Gujarati', 'Urdu',
	);

	/**
	 * @var OpenRouter
	 */
	private $client;

	/**
	 * @var Pexels
	 */
	private $pexels;

	public function __construct( OpenRouter $client = null, Pexels $pexels = null ) {
		$this->client = null !== $client ? $client : new OpenRouter();
		$this->pexels = null !== $pexels ? $pexels : new Pexels();
	}

	/**
	 * Debug logging (only when WP_DEBUG is on, so production sites stay quiet).
	 *
	 * @param string $message Log line.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Diflowrin AI Content Generation] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Generate a draft.
	 *
	 * @param array $args {
	 *     @type string $topic       Keyword or title (required unless $source_url is given).
	 *     @type string $source_url  Optional article URL to base the draft on.
	 *     @type string $tone        e.g. "professional".
	 *     @type string $length      short|medium|long.
	 *     @type string $language    e.g. "English".
	 *     @type bool   $with_image  Generate a featured image.
	 *     @type int    $image_count In-article images (0-4). Omit to use the Settings default.
	 *     @type bool   $ai_disclosure Append the AI-disclosure notice to the article.
	 * }
	 * @return array|WP_Error {post_id, edit_url, title, image (bool), image_url, image_error, images (int), images_error}
	 */
	public function generate( array $args ) {
		$topic      = isset( $args['topic'] ) ? trim( (string) $args['topic'] ) : '';
		$source_url = isset( $args['source_url'] ) ? trim( (string) $args['source_url'] ) : '';

		// A source URL alone is enough: the topic is derived from the page itself.
		if ( '' === $topic && '' === $source_url ) {
			return new WP_Error( 'diflowrin_content_generator_no_topic', __( 'Please enter a topic or a source URL.', 'diflowrin-ai-content-generation' ) );
		}

		$tone     = isset( $args['tone'] ) && '' !== $args['tone'] ? (string) $args['tone'] : 'professional';
		$length   = isset( $args['length'], self::LENGTHS[ $args['length'] ] ) ? $args['length'] : 'medium';
		$language = isset( $args['language'] ) && in_array( $args['language'], self::LANGUAGES, true ) ? (string) $args['language'] : 'English';
		$words    = self::LENGTHS[ $length ];
		$model    = Settings::get( 'model', Settings::DEFAULT_MODEL );

		// ------------------------------------------------------------------
		// Source material.
		//
		// With a URL, the page itself is the primary source: a direct fetch is
		// deterministic and works on pages Sonar's search index has never seen
		// (obscure GitHub repos, fresh posts). Sonar then runs as enrichment,
		// adding live web context — and doubles as the fallback reader when
		// the direct fetch fails (paywalls, bot walls).
		//
		// With only a topic, Sonar research IS the source material.
		// ------------------------------------------------------------------
		$source_text   = ''; // Primary material: page content, or research notes.
		$research_text = ''; // Sonar notes when they supplement a fetched page.
		$source_error  = '';
		$research_used = false;

		if ( '' !== $source_url ) {
			$this->log( 'Fetching source URL: ' . $source_url );
			$fetched = $this->fetch_source( $source_url );
			if ( is_wp_error( $fetched ) ) {
				$source_error = $fetched->get_error_message();
				$this->log( 'Direct fetch FAILED: ' . $source_error );
			} else {
				$source_text = $fetched;
				$this->log( sprintf( 'Direct fetch OK: %d chars scraped', strlen( $fetched ) ) );
			}

			if ( Settings::sonar_enabled() ) {
				// With the page already fetched, Sonar researches its subject (an
				// excerpt guides it); otherwise it is asked to read the URL itself.
				$excerpt = '' !== $source_text
					? ( function_exists( 'mb_substr' ) ? mb_substr( $source_text, 0, 1500 ) : substr( $source_text, 0, 1500 ) )
					: '';
				$this->log( sprintf( 'Sonar research start: topic="%s" url="%s" mode=%s', $topic, $source_url, '' !== $excerpt ? 'subject-from-excerpt' : 'read-url' ) );
				$research = $this->research_with_sonar( $topic, $source_url, $language, $excerpt );
				if ( is_wp_error( $research ) ) {
					// Only worth reporting when Sonar was the last hope for the page.
					$this->log( 'Sonar research FAILED: ' . $research->get_error_message() );
					if ( '' === $source_text && '' === $source_error ) {
						$source_error = $research->get_error_message();
					}
				} elseif ( '' === $source_text ) {
					// Direct fetch failed — Sonar's reading becomes the primary source.
					$source_text   = $research;
					$source_error  = '';
					$research_used = true;
					$this->log( sprintf( 'Sonar research OK (used as primary source): %d chars', strlen( $research ) ) );
				} else {
					$research_text = $research;
					$research_used = true;
					$this->log( sprintf( 'Sonar research OK (enrichment): %d chars of notes', strlen( $research ) ) );
				}
			}
		} elseif ( Settings::sonar_enabled() ) {
			$this->log( sprintf( 'Sonar research start: topic="%s"', $topic ) );
			$research = $this->research_with_sonar( $topic, '', $language );
			if ( is_wp_error( $research ) ) {
				$source_error = $research->get_error_message();
				$this->log( 'Sonar research FAILED: ' . $source_error );
			} else {
				$source_text   = $research;
				$research_used = true;
				$this->log( sprintf( 'Sonar research OK: %d chars of notes', strlen( $research ) ) );
			}
		}

		// Link-only generation needs actual source material to write from.
		if ( '' === $topic && '' === $source_text ) {
			return new WP_Error(
				'diflowrin_content_generator_source_unreadable',
				sprintf(
					/* translators: %s: reason the source could not be used. */
					__( 'The source URL could not be used and no topic was given, so there is nothing to write from. %s', 'diflowrin-ai-content-generation' ),
					$source_error
				)
			);
		}

		$content = $this->client->generate_text(
			$this->system_prompt(),
			$this->user_prompt( $topic, $tone, $words, $language, $source_text, $source_url, $research_text ),
			$model
		);
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$parsed = $this->parse( $content, '' !== $topic ? $topic : __( 'Draft from source URL', 'diflowrin-ai-content-generation' ) );

		$post_id = wp_insert_post(
			array(
				'post_title'   => $parsed['title'],
				'post_content' => $parsed['html'],
				'post_status'  => 'draft',
				'post_type'    => 'post',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = array(
			'post_id'       => $post_id,
			'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
			'title'         => $parsed['title'],
			'content'       => $parsed['html'],
			'image'         => false,
			'image_url'     => '',
			'image_error'   => '',
			'images'        => 0,
			'images_error'  => '',
			'ai_disclosure' => false,
			'source_used'   => '' !== $source_text,
			'source_error'  => $source_error,
			'research_used' => $research_used,
		);

		if ( ! empty( $args['with_image'] ) ) {
			$attached = $this->attach_featured_image( $post_id, $parsed['title'], $language );
			if ( is_wp_error( $attached ) ) {
				// A failed image must not fail the whole draft — surface it softly.
				$result['image_error'] = $attached->get_error_message();
			} else {
				$result['image'] = true;
				// The cover is not part of the body, so the caller gets its URL
				// separately — otherwise there is no way to show what was made.
				$result['image_url'] = (string) wp_get_attachment_image_url( $attached, 'medium' );
			}
		}

		// Everything below rewrites the body. One variable carries it, and one
		// wp_update_post at the end writes whatever came out — otherwise each
		// new step needs its own save and they start overwriting each other.
		$content = $parsed['html'];

		$image_count = isset( $args['image_count'] )
			? Settings::clamp_image_count( $args['image_count'] )
			: Settings::image_count();

		if ( $image_count > 0 ) {
			$woven                  = $this->add_body_images( $post_id, $parsed['title'], $content, $image_count, $language );
			$result['images']       = $woven['count'];
			$result['images_error'] = $woven['error'];
			$content                = $woven['html'];
		}

		if ( ! empty( $args['ai_disclosure'] ) ) {
			// Appended after the images, so it is genuinely the last thing in the
			// article rather than something an illustration can be placed below.
			$content                  = $this->append_disclosure( $content );
			$result['ai_disclosure']  = true;
		}

		if ( $content !== $parsed['html'] ) {
			$result['content'] = $content;
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				)
			);
		}

		return $result;
	}

	/**
	 * Append the AI-disclosure notice to the article body.
	 *
	 * @param string $html Article body.
	 * @return string
	 */
	private function append_disclosure( $html ) {
		$text = Settings::disclosure_text();
		if ( '' === $text ) {
			return $html;
		}

		$this->log( 'AI disclosure appended' );

		return $html . "\n<p class=\"diflowrin-ai-disclosure\"><em>" . esc_html( $text ) . '</em></p>';
	}

	/**
	 * @return string
	 */
	private function system_prompt() {
		$contract = 'You are an expert SEO content writer. You always return a single valid JSON object and nothing else. '
			. 'The JSON has exactly two string keys: "title" and "html". '
			. '"title" is an engaging, search-friendly headline (no quotes around it). '
			. '"html" is the article body as clean semantic HTML using <h2>, <h3>, <p>, <ul>, <ol> and <strong>. '
			. 'Do NOT include an <h1>, a document wrapper, markdown, code fences, or any text outside the JSON object.';

		return $contract . "\n\n" . $this->heading_rules() . "\n\n" . $this->humanizing_rules();
	}

	/**
	 * Rules that keep section headings specific to the article.
	 *
	 * Without them the model reaches for the same skeleton every time, and a
	 * site ends up with fifty articles that all open with "Introduction" and
	 * close with "Conclusion". That costs twice: the reader learns nothing from
	 * the heading, and search engines see a page whose structure says nothing
	 * about its subject. It also degrades this plugin's own image alt text,
	 * which is built from the section heading.
	 *
	 * The approach is the one the SEO Content Architect desktop app uses: give
	 * the article flow as an INTERNAL guide (see user_prompt) while banning
	 * those labels as headings, and require every heading to carry the
	 * article's own vocabulary.
	 *
	 * @return string
	 */
	private function heading_rules() {
		return "SECTION HEADINGS MUST BE SPECIFIC TO THIS ARTICLE:\n"
			. "- Never use a generic, reusable section heading. Banned in English AND in every other "
			. "language, so \"Introducere\", \"Concluzie\", \"Introducción\", \"Fazit\", \"まとめ\" and any "
			. "equivalent are banned too: Introduction, Conclusion, Overview, Summary, Final Thoughts, "
			. "Key Takeaways, Wrapping Up, Getting Started, Background, In This Article, What Is It.\n"
			. "- Each H2 names the specific thing its section is about, in the article's own vocabulary, "
			. "and reads like something a person would type into a search box.\n"
			. "- The opening section has an H2 of its own, like every other section. It must name what "
			. "that section actually covers, for example what the subject is or the problem it solves. "
			. "Replace the word \"Introduction\" with a real heading, do not simply drop the heading.\n"
			. "- The closing section is a section like any other. Its heading states the point it makes, "
			. "not the fact that the article is ending.\n"
			. "- Sentence case, no Title Case, and no numbering such as \"1.\" or \"Section 2\".\n"
			. "- Test each heading before you keep it: if it would fit an article on a completely "
			. "different subject, it is too generic. Rewrite it.";
	}

	/**
	 * Anti-AI-writing rules (adapted from the "humanizer" skill / Wikipedia "Signs of AI writing")
	 * so generated articles read like a knowledgeable human wrote them.
	 *
	 * @return string
	 */
	private function humanizing_rules() {
		return "WRITE LIKE A KNOWLEDGEABLE HUMAN, NOT AN AI. Follow these rules strictly:\n"
			. "- Never use em dashes or en dashes. Use periods, commas, colons, or parentheses instead. Zero dashes of that kind in the output. This is a hard rule.\n"
			. "- Ban AI-tell vocabulary: delve, tapestry, landscape, testament, showcase, boasts, realm, foster, underscore, interplay, vibrant, nestled, breathtaking, pivotal, robust, seamless, elevate, unleash, embark, ever-evolving, in today's fast-paced world.\n"
			. "- Use direct verbs. Prefer \"is\" and \"has\" over \"serves as\", \"features\", \"boasts\".\n"
			. "- No significance inflation or promotional phrasing (\"marks a pivotal moment\", \"stands as a testament\"). State concrete, verifiable facts.\n"
			. "- No vague authorities (\"experts believe\", \"studies show\") without a specific source. If you cannot attribute it, make a plain claim or drop it.\n"
			. "- Avoid empty \"-ing\" constructions that fake depth (\"symbolizing\", \"reflecting\", \"highlighting\"). Write direct statements.\n"
			. "- Do not force groups of three. Use the natural number of items.\n"
			. "- Do not cycle synonyms to avoid repetition. Repeat the clearest term.\n"
			. "- Avoid \"It's not just X, it's Y\" and negative-parallelism constructions.\n"
			. "- No signposting or filler openers (\"Let's dive in\", \"In today's world\", \"In this article we will\", \"Here's what you need to know\").\n"
			. "- No chatbot closers (\"I hope this helps\", \"In conclusion\"). End on a substantive final point.\n"
			. "- Vary sentence length. Mix short and longer sentences for natural rhythm.\n"
			. "- Do not overuse bold. Emphasize sparingly and only where it helps.\n"
			. "- Cut redundant phrasing (\"in order to\" becomes \"to\", \"due to the fact that\" becomes \"because\").\n"
			. "- Prefer specific, concrete details over generic statements. Write in active voice and name the actor.";
	}

	/**
	 * Run a Perplexity Sonar research pass (via OpenRouter) and return the
	 * findings as source material for the writing model.
	 *
	 * With a URL: Sonar reads the page and summarises its actual content.
	 * Without one: Sonar researches the topic on the live web (facts, figures,
	 * recent developments) so the article is grounded in current information.
	 *
	 * @param string $topic        Topic (may be empty when $source_url is given).
	 * @param string $source_url   Source URL (may be empty).
	 * @param string $language     Target article language.
	 * @param string $page_excerpt Excerpt of the already-fetched page. When present,
	 *                             Sonar researches the page's SUBJECT on the web instead
	 *                             of re-reading the URL (Perplexity cannot fetch many
	 *                             URLs directly — e.g. GitHub — but subject research works).
	 * @return string|WP_Error Research notes.
	 */
	private function research_with_sonar( $topic, $source_url, $language, $page_excerpt = '' ) {
		$sonar_model = Settings::get( 'sonar_model', Settings::DEFAULT_SONAR_MODEL );

		$system = 'You are a meticulous research assistant with live web access. '
			. 'You return factual research notes as plain text: key facts, names, numbers, dates and direct findings. '
			. 'No introductions, no meta commentary, no markdown headings. Just dense, well-organised notes.';

		if ( '' !== $page_excerpt ) {
			$user = "Below is an excerpt from an article I already have. Research its subject on the live web for SUPPLEMENTARY information.\n\n"
				. "Excerpt:\n\"\"\"\n" . $page_excerpt . "\n\"\"\"\n\n"
				. "Report:\n"
				. "- Current facts, statistics and figures about this subject, with their dates\n"
				. "- Recent developments or news the excerpt does not mention\n"
				. "- Relevant context: main players, alternatives, common questions people ask\n"
				. ( '' !== $topic ? '- Focus on aspects relevant to the topic "' . $topic . "\"\n" : '' )
				. 'Write the notes in ' . $language . '.';
		} elseif ( '' !== $source_url ) {
			$user = 'Read the web page at this URL and report what it actually says: ' . $source_url . "\n\n"
				. "Extract:\n"
				. "- The main subject and the page's core claims or story\n"
				. "- Every important fact, number, date, name and quote\n"
				. "- The overall structure of the argument or narrative\n"
				. ( '' !== $topic ? '- Focus on aspects relevant to the topic "' . $topic . "\"\n" : '' )
				. "If the page cannot be accessed or its content cannot be determined, reply with exactly: SOURCE_UNAVAILABLE\n"
				. 'Write the notes in ' . $language . '.';
		} else {
			$user = 'Research the topic "' . $topic . "\" on the web.\n\n"
				. "Report:\n"
				. "- The current state of the subject: key facts, statistics and figures with their dates\n"
				. "- Recent developments and anything time-sensitive a reader should know\n"
				. "- Names of the main players, products or organisations involved\n"
				. "- Common questions people ask about it, with short factual answers\n"
				. 'Write the notes in ' . $language . '.';
		}

		$notes = $this->client->generate_text( $system, $user, $sonar_model );
		if ( is_wp_error( $notes ) ) {
			return $notes;
		}

		// Sonar's own signal that it could not read the page.
		if ( false !== strpos( $notes, 'SOURCE_UNAVAILABLE' ) ) {
			return new WP_Error( 'diflowrin_content_generator_sonar_unavailable', __( 'Sonar could not access the source URL.', 'diflowrin-ai-content-generation' ) );
		}

		// Strip citation markers like [1][2] so they don't leak into the article.
		$notes = preg_replace( '/\[\d+\]/', '', $notes );
		$notes = trim( preg_replace( '/[ \t]{2,}/', ' ', $notes ) );

		if ( strlen( $notes ) < 100 ) {
			return new WP_Error( 'diflowrin_content_generator_sonar_thin', __( 'Sonar returned too little research material.', 'diflowrin-ai-content-generation' ) );
		}

		// Cap length to keep the prompt reasonable.
		return function_exists( 'mb_substr' ) ? mb_substr( $notes, 0, 8000 ) : substr( $notes, 0, 8000 );
	}

	/**
	 * @param string $topic
	 * @param string $tone
	 * @param int    $words
	 * @param string $language
	 * @param string $source_text   Primary material: scraped page text or research notes.
	 * @param string $source_url    Original source URL (for context only).
	 * @param string $research_text Supplementary Sonar research notes (may be empty).
	 * @return string
	 */
	private function user_prompt( $topic, $tone, $words, $language, $source_text = '', $source_url = '', $research_text = '' ) {
		$topic_line = '' !== $topic
			? sprintf( 'Topic: %s', $topic )
			: 'Topic: derive the topic from the source material below and cover its subject thoroughly.';

		// The flow below is an INTERNAL guide, never a set of headings — spelling
		// out "include an introduction and a conclusion", as this prompt used to,
		// is exactly what produced an <h2>Introduction</h2> on every article.
		$prompt = sprintf(
			"Write an SEO-optimized article.\n%s\nTone: %s\nTarget length: about %d words.\nLanguage: %s\n"
			. "Use clear H2/H3 structure and short paragraphs.\n"
			. "Follow this flow internally. These are descriptions of what each part DOES, not headings, "
			. "and none of these words may appear as a heading:\n"
			. "  1. Open with the subject itself: what it is and why it matters to the reader. This part "
			. "gets its own H2, named after what it covers.\n"
			. "  2. How it works, or what it consists of.\n"
			. "  3. Concrete detail: specifics, examples, comparisons, numbers.\n"
			. "  4. What the reader should do with this, in practice.\n"
			. "  5. A closing section that lands the main point, headed by that point.\n"
			. 'Return only the JSON object described in the system message.',
			$topic_line,
			$tone,
			$words,
			$language
		);

		if ( '' !== $source_text ) {
			$origin = '' !== $source_url
				? sprintf( 'The material below was gathered from this source: %s.', $source_url )
				: 'The material below is up-to-date research on the topic.';
			$prompt .= "\n\n" . $origin
				. " Base the article primarily on it. Keep the facts accurate, "
				. "rewrite everything in your own words, and do not copy sentences verbatim:\n\"\"\"\n"
				. $source_text
				. "\n\"\"\"";
		}

		if ( '' !== $research_text ) {
			$prompt .= "\n\nAdditional up-to-date web research on the subject. Use it to enrich the article "
				. 'with current facts and context, but the source material above remains the primary basis. '
				. "If the research contradicts the source material, prefer the source material:\n\"\"\"\n"
				. $research_text
				. "\n\"\"\"";
		}

		return $prompt;
	}

	/**
	 * Fetch a URL and return its readable text as source material.
	 *
	 * @param string $url
	 * @return string|WP_Error
	 */
	private function fetch_source( $url ) {
		$url = esc_url_raw( $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'diflowrin_content_generator_bad_url', __( 'The source URL is not valid.', 'diflowrin-ai-content-generation' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'            => 20,
				'redirection'        => 3,
				'user-agent'         => 'DiflowrinContentGenerator/' . DIFLOWRIN_CG_VERSION . '; ' . home_url( '/' ),
				// SSRF hardening: re-validates the URL on every redirect hop,
				// so a public page cannot bounce the request to a private host.
				'reject_unsafe_urls' => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'diflowrin_content_generator_source_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Could not fetch the source URL (HTTP %d).', 'diflowrin-ai-content-generation' ),
					$code
				)
			);
		}

		$text = $this->extract_text( wp_remote_retrieve_body( $response ) );
		if ( '' === $text ) {
			return new WP_Error( 'diflowrin_content_generator_source_notext', __( 'No readable text was found at the source URL.', 'diflowrin-ai-content-generation' ) );
		}

		return $text;
	}

	/**
	 * Extract plain readable text from an HTML document (best-effort, no external libs).
	 *
	 * @param string $html
	 * @return string
	 */
	private function extract_text( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return '';
		}

		// Drop non-content blocks before stripping tags.
		$html = preg_replace( '#<(script|style|noscript|template|svg)\b[^>]*>.*?</\1>#is', ' ', $html );

		// Prefer the <body> if present.
		if ( preg_match( '#<body\b[^>]*>(.*?)</body>#is', $html, $m ) ) {
			$html = $m[1];
		}

		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );

		// Cap length to keep the prompt reasonable.
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 6000 ) : substr( $text, 0, 6000 );
	}

	/**
	 * Parse the model output into title + sanitised HTML, with resilient fallbacks.
	 *
	 * @param string $content Raw model content.
	 * @param string $topic   Fallback title.
	 * @return array{title:string,html:string}
	 */
	private function parse( $content, $topic ) {
		$content = trim( $content );

		// Strip accidental ```json fences.
		$content = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $content );

		$data = json_decode( $content, true );

		// Fallback: extract the first {...} block if extra prose slipped in.
		if ( ! is_array( $data ) && preg_match( '/\{.*\}/s', $content, $m ) ) {
			$data = json_decode( $m[0], true );
		}

		if ( is_array( $data ) && isset( $data['html'] ) ) {
			$title = isset( $data['title'] ) && '' !== trim( (string) $data['title'] )
				? sanitize_text_field( $data['title'] )
				: $topic;
			$html  = (string) $data['html'];
		} else {
			// Last resort: treat the whole thing as the body.
			$title = $topic;
			$html  = $content;
		}

		return array(
			'title' => $title,
			'html'  => wp_kses_post( $html ),
		);
	}

	/**
	 * Generate an image and set it as the post's featured image.
	 *
	 * @param int    $post_id
	 * @param string $title
	 * @param string $language Article language.
	 * @return int|WP_Error Attachment ID.
	 */
	private function attach_featured_image( $post_id, $title, $language = 'English' ) {
		$this->log( 'Featured image: acquiring from ' . Settings::image_source() );

		$image = $this->acquire_image( $title, '', true, $language );
		if ( is_wp_error( $image ) ) {
			$this->log( 'Featured image FAILED: ' . $image->get_error_message() );
			return $image;
		}

		$alt = ! empty( $image['alt'] ) ? $image['alt'] : $title;
		// A featured image sits outside the body, so its credit cannot go in a
		// figcaption. The attachment caption is where themes look for it.
		$attachment_id = $this->store_image( $post_id, $image, $alt );
		if ( is_wp_error( $attachment_id ) ) {
			$this->log( 'Featured image upload FAILED: ' . $attachment_id->get_error_message() );
			return $attachment_id;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		$this->log( 'Featured image OK: attachment ' . $attachment_id );

		return $attachment_id;
	}

	// -----------------------------------------------------------------
	// In-article images
	// -----------------------------------------------------------------

	/**
	 * Generate up to $count illustrations and weave them into the article body,
	 * one per section, each uploaded to the media library and attached to the post.
	 *
	 * A failed image never fails the draft: whatever was produced is kept and the
	 * reason is handed back for the UI to show.
	 *
	 * @param int    $post_id  Post the images are attached to.
	 * @param string $title    Article title (context for every prompt).
	 * @param string $html     Article body.
	 * @param int    $count    How many images to generate.
	 * @param string $language Article language.
	 * @return array{html:string,count:int,error:string}
	 */
	private function add_body_images( $post_id, $title, $html, $count, $language = 'English' ) {
		$anchors = $this->image_anchors( $html, $count );
		if ( empty( $anchors ) ) {
			$this->log( 'In-article images: no place to insert them, skipping' );
			return array(
				'html'  => $html,
				'count' => 0,
				'error' => __( 'The article had no sections or paragraphs to place images in.', 'diflowrin-ai-content-generation' ),
			);
		}

		$inserts = array();
		$error   = '';

		foreach ( $anchors as $index => $anchor ) {
			$this->log( sprintf( 'In-article image %d/%d: acquiring for "%s"', $index + 1, count( $anchors ), $anchor['label'] ) );

			$image = $this->acquire_image( $title, $anchor['label'], false, $language );
			if ( is_wp_error( $image ) ) {
				// Image failures are almost always systemic (wrong model slug, no
				// credit, rate limit, bad key). Stopping here avoids paying for
				// the rest only to collect the same error four times.
				$error = $image->get_error_message();
				$this->log( 'In-article image FAILED, stopping: ' . $error );
				break;
			}

			// A stock photo describes itself better than the heading does; a
			// generated one has no description of its own.
			$label = '' !== $anchor['label'] ? $anchor['label'] : $title;
			$alt   = ! empty( $image['alt'] ) ? $image['alt'] : $label;

			$attachment_id = $this->store_image( $post_id, $image, $alt );
			if ( is_wp_error( $attachment_id ) ) {
				$error = $attachment_id->get_error_message();
				$this->log( 'In-article image upload FAILED, stopping: ' . $error );
				break;
			}

			$figure = $this->figure_html( $attachment_id, $alt, isset( $image['credit'] ) ? $image['credit'] : array() );
			if ( '' === $figure ) {
				continue;
			}

			$inserts[] = array(
				'offset' => $anchor['offset'],
				'html'   => $figure,
			);
		}

		// Splice from the end of the document backwards so each insertion cannot
		// shift the offsets of the ones still to come.
		$ordered = array_reverse( $inserts );
		foreach ( $ordered as $insert ) {
			$html = substr_replace( $html, "\n" . $insert['html'] . "\n", $insert['offset'], 0 );
		}

		$this->log( sprintf( 'In-article images: %d inserted', count( $inserts ) ) );

		return array(
			'html'  => $html,
			'count' => count( $inserts ),
			'error' => $error,
		);
	}

	/**
	 * Where images go in the body, in document order: after the first paragraph
	 * of each H2 section, so the heading keeps its opening lines and the image
	 * illustrates the section it sits in.
	 *
	 * @param string $html  Article body.
	 * @param int    $count How many spots are needed.
	 * @return array<int,array{offset:int,label:string}>
	 */
	private function image_anchors( $html, $count ) {
		$candidates = array();

		if ( preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $i => $heading ) {
				$after     = $heading[1] + strlen( $heading[0] );
				$paragraph = strpos( $html, '</p>', $after );
				$candidates[] = array(
					'offset' => false !== $paragraph ? $paragraph + 4 : $after,
					'label'  => trim( wp_strip_all_tags( $matches[1][ $i ][0] ) ),
				);
			}
		}

		// Articles without headings still get images, spaced between paragraphs.
		// The first boundary is skipped so the intro is not interrupted.
		if ( empty( $candidates ) && preg_match_all( '#</p>#i', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( array_slice( $matches[0], 1 ) as $paragraph ) {
				$candidates[] = array(
					'offset' => $paragraph[1] + strlen( $paragraph[0] ),
					'label'  => '',
				);
			}
		}

		return $this->spread( $candidates, $count );
	}

	/**
	 * Pick $count entries spaced evenly across $candidates, keeping their order.
	 * With three images and nine sections that lands one near the top, one in the
	 * middle and one towards the end, instead of three clustered at the start.
	 *
	 * @param array $candidates Anchors in document order.
	 * @param int   $count      How many to keep.
	 * @return array
	 */
	private function spread( array $candidates, $count ) {
		$total = count( $candidates );
		if ( 0 === $total || $count < 1 ) {
			return array();
		}
		if ( $total <= $count ) {
			return $candidates;
		}

		$step   = $total / $count;
		$picked = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$picked[ (int) floor( $i * $step ) ] = true;
		}

		return array_values( array_intersect_key( $candidates, $picked ) );
	}

	/**
	 * Get one image from whichever source the site is configured to use.
	 *
	 * The two are deliberately interchangeable: a model invents a picture from a
	 * prompt, Pexels finds a real photograph from a search query, and both hand
	 * back the same {bytes, mime} shape. Pexels results additionally carry the
	 * photographer credit and the photo's own description.
	 *
	 * @param string $title       Article title.
	 * @param string $section     Section heading, or '' for the featured image.
	 * @param bool   $is_featured Whether this is the cover image.
	 * @param string $language    Article language.
	 * @return array{bytes:string,mime:string}|WP_Error
	 */
	private function acquire_image( $title, $section, $is_featured, $language ) {
		if ( Settings::uses_pexels() ) {
			return $this->pexels->find_photo( Pexels::build_query( $section, $title ) );
		}

		return $this->client->generate_image(
			$this->image_prompt( $title, $section, $is_featured, $language ),
			Settings::image_model()
		);
	}

	/**
	 * Build the prompt for one image.
	 *
	 * Note: no sprintf here. A title or heading containing a "%" would be read as
	 * a format specifier and mangle the prompt (same rule as the research pass).
	 *
	 * @param string $title       Article title.
	 * @param string $section     Section heading, or '' for the featured image.
	 * @param bool   $is_featured Whether this is the cover image.
	 * @param string $language    Article language, used when text is allowed.
	 * @return string
	 */
	private function image_prompt( $title, $section, $is_featured, $language = 'English' ) {
		$prompt = $is_featured
			? 'A high-quality, professional blog featured image in a wide 16:9 composition, representing this article: ' . $title . '.'
			: 'A high-quality, professional editorial illustration in a landscape composition for the section "' . $section . '" of an article about: ' . $title . '.';

		$style = Settings::image_style();
		if ( '' !== $style ) {
			$prompt .= ' Visual style: ' . $style . '.';
		}

		$prompt .= ' Clean, uncluttered composition.';

		if ( Settings::image_text_allowed() ) {
			// The user has vouched for their model's typography. Still worth being
			// explicit about the language: left to itself a model writes English
			// words onto an article in any other language, and about placeholders,
			// which are the classic failure even on models that spell well.
			$prompt .= ' Any words shown in the image must be spelled correctly and written in ' . $language
				. '. No placeholder text, no lorem ipsum, no watermark.';
		} else {
			// Rendered text is the giveaway of an AI image: most models misspell it,
			// and a wrong word baked into a picture cannot be translated or read out
			// by a screen reader.
			$prompt .= ' No text, no words, no letters, no logos, no watermark.';
		}

		return $prompt;
	}

	/**
	 * Put generated bytes in the media library, attached to the post.
	 *
	 * @param int    $post_id Parent post.
	 * @param array  $image   {bytes, mime} from OpenRouter::generate_image().
	 * @param string $label   Title and alt text for the attachment.
	 * @return int|WP_Error Attachment ID.
	 */
	private function store_image( $post_id, array $image, $label ) {
		$extension = RemoteImage::extension( $image['mime'] );
		$slug      = sanitize_title( $label );
		if ( '' === $slug ) {
			$slug = 'ai-image';
		}

		// wp_upload_bits() de-duplicates the filename itself, so several images
		// from the same post can share this base name.
		$upload = wp_upload_bits( $slug . '-' . $post_id . '.' . $extension, null, $image['bytes'] );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'diflowrin_content_generator_upload', $upload['error'] );
		}

		// post_excerpt IS the attachment caption in WordPress. Carrying the
		// credit here means the attribution travels with the file: it shows
		// under a featured image, in the media library, and anywhere the photo
		// is reused later.
		$caption = isset( $image['credit'] ) ? $this->credit_text( $image['credit'] ) : '';

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $image['mime'],
				'post_title'     => $label,
				'post_content'   => '',
				'post_excerpt'   => $caption,
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$post_id,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// wp_generate_attachment_metadata() is defined in wp-admin/includes/image.php; load it right before use.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

		// WordPress does not derive alt text, and an image without it is invisible
		// to anyone using a screen reader.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $label );

		return $attachment_id;
	}

	/**
	 * Markup for an inserted body image, with the photographer credit when the
	 * photo came from a stock library.
	 *
	 * Built with wp_get_attachment_image() rather than a hand-written <img>.
	 * That matters: the images are large (a Pexels photo arrives around 1880px
	 * wide), and a bare <img src> with no width/height renders at its natural
	 * size, blowing straight through the content column on any theme whose CSS
	 * does not happen to constrain it. WordPress emits width, height, srcset,
	 * sizes and loading="lazy", and points src at the "large" size instead of
	 * the full original — so the browser reserves the right box before the file
	 * arrives and downloads a sensibly sized one.
	 *
	 * @param int    $attachment_id Attachment to render.
	 * @param string $alt           Alt text.
	 * @param array  $credit        {photographer, photographer_url, url}; empty for generated images.
	 * @return string Empty when the attachment cannot be rendered.
	 */
	private function figure_html( $attachment_id, $alt, array $credit = array() ) {
		$img = wp_get_attachment_image(
			$attachment_id,
			'large',
			false,
			array(
				'alt' => $alt,
				// Belt and braces for themes that do not load the block styles:
				// the attributes above size the box, this keeps it inside the
				// column whatever the container width turns out to be.
				'style' => 'max-width:100%;height:auto;',
			)
		);

		if ( '' === $img ) {
			return '';
		}

		$html    = '<figure class="wp-block-image size-large">' . $img;
		$caption = $this->credit_html( $credit );
		if ( '' !== $caption ) {
			$html .= '<figcaption class="wp-element-caption">' . $caption . '</figcaption>';
		}

		return $html . '</figure>';
	}

	/**
	 * The attribution line for a stock photo.
	 *
	 * Not optional: the Pexels API terms require crediting the photographer and
	 * linking back to Pexels wherever their photos are shown. Leaving it to the
	 * user to remember would put their site in breach, so it is written into the
	 * markup at the point the image is placed.
	 *
	 * @param array $credit {photographer, photographer_url, url}.
	 * @return string Empty when the image has no credit (i.e. it was generated).
	 */
	private function credit_html( array $credit ) {
		if ( empty( $credit['photographer'] ) ) {
			return '';
		}

		$photographer = esc_html( $credit['photographer'] );
		if ( ! empty( $credit['photographer_url'] ) ) {
			$photographer = '<a href="' . esc_url( $credit['photographer_url'] ) . '" rel="nofollow noopener" target="_blank">' . $photographer . '</a>';
		}

		$pexels_url = ! empty( $credit['url'] ) ? $credit['url'] : 'https://www.pexels.com';
		$pexels     = '<a href="' . esc_url( $pexels_url ) . '" rel="nofollow noopener" target="_blank">Pexels</a>';

		return sprintf(
			/* translators: 1: photographer name (may be a link), 2: link reading "Pexels". */
			esc_html__( 'Photo by %1$s on %2$s', 'diflowrin-ai-content-generation' ),
			$photographer,
			$pexels
		);
	}

	/**
	 * Plain-text attribution, for places that cannot hold markup.
	 *
	 * @param array $credit {photographer, url}.
	 * @return string
	 */
	private function credit_text( array $credit ) {
		if ( empty( $credit['photographer'] ) ) {
			return '';
		}
		return sprintf(
			/* translators: 1: photographer name, 2: link to the photo on Pexels. */
			__( 'Photo by %1$s on Pexels: %2$s', 'diflowrin-ai-content-generation' ),
			$credit['photographer'],
			! empty( $credit['url'] ) ? $credit['url'] : 'https://www.pexels.com'
		);
	}
}
