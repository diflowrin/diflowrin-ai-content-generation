<?php

namespace Diflowrin\ContentGenerator\Admin;

use Diflowrin\ContentGenerator\Connect\Connect;
use Diflowrin\ContentGenerator\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu, asset loading and page routing.
 */
class Admin {

	const CAP = 'manage_options';

	/**
	 * Page hook suffixes we own (for scoped asset loading).
	 *
	 * @var string[]
	 */
	private $hooks = array();

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Connect
	 */
	private $connect;

	/**
	 * @var Ajax
	 */
	private $ajax;

	public function __construct() {
		$this->settings = new Settings();
		$this->connect  = new Connect();
		$this->ajax     = new Ajax();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . DIFLOWRIN_CG_BASENAME, array( $this, 'action_links' ) );

		// Register the settings storage (Settings API).
		add_action( 'admin_init', array( $this->settings, 'register' ) );
	}

	/**
	 * Top-level menu + submenus.
	 */
	public function register_menu() {
		$this->hooks[] = add_menu_page(
			// Page title (browser tab) keeps the full plugin name; the sidebar label
			// is kept short so it does not wrap onto three lines in the admin menu.
			__( 'Diflowrin AI Content Generation', 'diflowrin-ai-content-generation' ),
			__( 'AI Content', 'diflowrin-ai-content-generation' ),
			self::CAP,
			DIFLOWRIN_CG_SLUG,
			array( $this, 'render_connect' ),
			'dashicons-edit-page',
			58
		);

		// First submenu mirrors the parent (Connect dashboard).
		add_submenu_page(
			DIFLOWRIN_CG_SLUG,
			__( 'Connect', 'diflowrin-ai-content-generation' ),
			__( 'Connect', 'diflowrin-ai-content-generation' ),
			self::CAP,
			DIFLOWRIN_CG_SLUG,
			array( $this, 'render_connect' )
		);

		$this->hooks[] = add_submenu_page(
			DIFLOWRIN_CG_SLUG,
			__( 'Generate', 'diflowrin-ai-content-generation' ),
			__( 'Generate', 'diflowrin-ai-content-generation' ),
			self::CAP,
			DIFLOWRIN_CG_SLUG . '-generate',
			array( $this, 'render_generate' )
		);

		$this->hooks[] = add_submenu_page(
			DIFLOWRIN_CG_SLUG,
			__( 'Settings', 'diflowrin-ai-content-generation' ),
			__( 'Settings', 'diflowrin-ai-content-generation' ),
			self::CAP,
			DIFLOWRIN_CG_SLUG . '-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Load our CSS/JS only on our own screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, $this->hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'diflowrin-ai-content-generation-admin',
			DIFLOWRIN_CG_URL . 'assets/css/admin.css',
			array(),
			DIFLOWRIN_CG_VERSION
		);

		wp_enqueue_script(
			'diflowrin-ai-content-generation-admin',
			DIFLOWRIN_CG_URL . 'assets/js/admin.js',
			array(),
			DIFLOWRIN_CG_VERSION,
			true
		);

		wp_localize_script(
			'diflowrin-ai-content-generation-admin',
			'diflowrin_content_generator_data',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => Ajax::ACTION,
				'nonce'   => wp_create_nonce( Ajax::NONCE ),
				'hasKey'  => Settings::has_api_key(),
				'i18n'    => array(
					'generating'        => __( 'Generating…', 'diflowrin-ai-content-generation' ),
					'failed'            => __( 'Generation failed.', 'diflowrin-ai-content-generation' ),
					'noKey'             => __( 'Add your OpenRouter API key in Settings first.', 'diflowrin-ai-content-generation' ),
					'draftCreated'      => __( 'Draft created.', 'diflowrin-ai-content-generation' ),
					'draftCreatedImage' => __( 'Draft created with featured image.', 'diflowrin-ai-content-generation' ),
					'editDraft'         => __( 'Edit draft', 'diflowrin-ai-content-generation' ),
					'featuredImage'     => __( 'Featured image (set as the post thumbnail)', 'diflowrin-ai-content-generation' ),
					'imageSkipped'      => __( 'Featured image skipped:', 'diflowrin-ai-content-generation' ),
					'imagesSkipped'     => __( 'In-article images stopped:', 'diflowrin-ai-content-generation' ),
					'disclosureAdded'   => __( 'AI disclosure added at the end of the article.', 'diflowrin-ai-content-generation' ),
					'sourceUsed'        => __( 'Based on the source URL you provided.', 'diflowrin-ai-content-generation' ),
					'sourceSkipped'     => __( 'Source URL skipped:', 'diflowrin-ai-content-generation' ),
					'researchUsed'      => __( 'Grounded in live Sonar web research.', 'diflowrin-ai-content-generation' ),
					'researchSkipped'   => __( 'Sonar research skipped:', 'diflowrin-ai-content-generation' ),
					'noInput'           => __( 'Enter a topic or a source URL first.', 'diflowrin-ai-content-generation' ),
				),
			)
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=' . DIFLOWRIN_CG_SLUG . '-settings' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'diflowrin-ai-content-generation' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Connect / dashboard screen.
	 */
	public function render_connect() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'diflowrin-ai-content-generation' ) );
		}
		$connect = $this->connect;
		require DIFLOWRIN_CG_PATH . 'includes/Admin/views/connect.php';
	}

	/**
	 * Generate screen.
	 */
	public function render_generate() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'diflowrin-ai-content-generation' ) );
		}
		require DIFLOWRIN_CG_PATH . 'includes/Admin/views/generate.php';
	}

	/**
	 * Settings screen.
	 */
	public function render_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'diflowrin-ai-content-generation' ) );
		}
		$settings = $this->settings;
		require DIFLOWRIN_CG_PATH . 'includes/Admin/views/settings.php';
	}
}
