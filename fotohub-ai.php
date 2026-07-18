<?php
/**
 * Plugin Name: FOTOhub AI — Creative Powerhouse
 * Plugin URI: https://fotohub.app/integrations/wordpress
 * Description: Full-featured AI creative suite: image generation, video generation, Stability AI tools, AI copywriting, music/audio generation, analytics, and scheduled batch processing powered by FOTOhub.
 * Version: 2.0.0
 * Author: FOTOhub
 * Author URI: https://fotohub.app
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fotohub-ai
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FOTOHUB_AI_VERSION', '2.0.0' );
define( 'FOTOHUB_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOTOHUB_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FOTOHUB_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FOTOHUB_AI_API_BASE', 'https://apis.fotohub.app' );

/**
 * Main plugin class.
 */
final class FotohubAI {

	/**
	 * Single instance.
	 *
	 * @var FotohubAI|null
	 */
	private static ?FotohubAI $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies(): void {
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-api.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-admin.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-media.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-bulk.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-ajax.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-video.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-stability.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-copywriter.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-analytics.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-scheduler.php';
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'check_requirements' ) );

		// Initialize core components.
		Fotohub_Admin::init();
		Fotohub_Media::init();
		Fotohub_Ajax::init();
		Fotohub_Stability::init();
		Fotohub_Copywriter::init();
		Fotohub_Analytics::init();
		Fotohub_Scheduler::init();

		// Conditionally load WooCommerce integration.
		if ( class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			Fotohub_Bulk::init();
			Fotohub_Video::init();
		}
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'fotohub-ai', false, dirname( FOTOHUB_AI_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Check minimum requirements.
	 */
	public function check_requirements(): void {
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'FOTOhub AI requires PHP 8.0 or higher.', 'fotohub-ai' );
				echo '</p></div>';
			} );
		}
	}

	/**
	 * Plugin activation hook.
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( FOTOHUB_AI_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'FOTOhub AI requires PHP 8.0 or higher.', 'fotohub-ai' ),
				'Plugin activation error',
				array( 'back_link' => true )
			);
		}

		// Set default options.
		add_option( 'fotohub_ai_api_key', '' );
		add_option( 'fotohub_ai_default_model', 'seedream-5-0-260128' );
		add_option( 'fotohub_ai_default_width', 1024 );
		add_option( 'fotohub_ai_default_height', 1024 );
		add_option( 'fotohub_ai_default_video_model', 'veo-2' );
		add_option( 'fotohub_ai_scheduler_batch_size', 5 );
		add_option( 'fotohub_ai_scheduler_notify_email', get_option( 'admin_email' ) );

		// Install custom database tables.
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-analytics.php';
		require_once FOTOHUB_AI_PLUGIN_DIR . 'includes/class-fotohub-scheduler.php';
		Fotohub_Analytics::install_table();
		Fotohub_Scheduler::install_table();

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'fotohub_ai_clear_cache' );
		wp_clear_scheduled_hook( 'fotohub_process_queue' );
		wp_clear_scheduled_hook( 'fotohub_nightly_batch' );
		wp_clear_scheduled_hook( 'fotohub_video_poll_jobs' );
		flush_rewrite_rules();
	}
}

// Activation/deactivation hooks.
register_activation_hook( __FILE__, array( 'FotohubAI', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FotohubAI', 'deactivate' ) );

/**
 * Initialize plugin.
 */
function fotohub_ai(): FotohubAI {
	return FotohubAI::instance();
}

// Boot the plugin.
fotohub_ai();
