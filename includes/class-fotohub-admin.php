<?php
/**
 * FOTOhub Admin Settings.
 *
 * Handles the admin settings page and plugin configuration.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings class.
 */
class Fotohub_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . FOTOHUB_AI_PLUGIN_BASENAME, array( __CLASS__, 'add_action_links' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public static function add_menu_pages(): void {
		// Top-level FOTOhub menu.
		add_menu_page(
			__( 'FOTOhub AI', 'fotohub-ai' ),
			__( 'FOTOhub AI', 'fotohub-ai' ),
			'manage_options',
			'fotohub-ai',
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-art',
			30
		);

		// Settings submenu (same as top level).
		add_submenu_page(
			'fotohub-ai',
			__( 'Settings', 'fotohub-ai' ),
			__( 'Settings', 'fotohub-ai' ),
			'manage_options',
			'fotohub-ai',
			array( __CLASS__, 'render_settings_page' )
		);

		// Stability Tools submenu.
		add_submenu_page(
			'fotohub-ai',
			__( 'Stability Tools', 'fotohub-ai' ),
			__( 'Stability Tools', 'fotohub-ai' ),
			'upload_files',
			'fotohub-ai-stability',
			array( __CLASS__, 'render_stability_page' )
		);

		// Analytics submenu.
		add_submenu_page(
			'fotohub-ai',
			__( 'Analytics', 'fotohub-ai' ),
			__( 'Analytics', 'fotohub-ai' ),
			'manage_options',
			'fotohub-ai-analytics',
			array( __CLASS__, 'render_analytics_page' )
		);

		// Scheduler submenu.
		add_submenu_page(
			'fotohub-ai',
			__( 'Job Scheduler', 'fotohub-ai' ),
			__( 'Scheduler', 'fotohub-ai' ),
			'manage_options',
			'fotohub-ai-scheduler',
			array( __CLASS__, 'render_scheduler_page' )
		);

		// Bulk generation submenu.
		add_submenu_page(
			'fotohub-ai',
			__( 'Bulk Generate', 'fotohub-ai' ),
			__( 'Bulk Generate', 'fotohub-ai' ),
			'manage_options',
			'fotohub-ai-bulk',
			array( __CLASS__, 'render_bulk_page' )
		);

		// Also keep legacy settings page link for backward compatibility.
		add_options_page(
			__( 'FOTOhub AI Settings', 'fotohub-ai' ),
			__( 'FOTOhub AI', 'fotohub-ai' ),
			'manage_options',
			'fotohub-ai-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public static function register_settings(): void {
		register_setting( 'fotohub_ai_settings', 'fotohub_ai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_api_key' ),
		) );

		register_setting( 'fotohub_ai_settings', 'fotohub_ai_default_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'seedream-5-0-260128',
		) );

		register_setting( 'fotohub_ai_settings', 'fotohub_ai_default_width', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1024,
		) );

		register_setting( 'fotohub_ai_settings', 'fotohub_ai_default_height', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1024,
		) );

		// Settings section.
		add_settings_section(
			'fotohub_ai_general',
			__( 'API Configuration', 'fotohub-ai' ),
			array( __CLASS__, 'render_section_description' ),
			'fotohub-ai-settings'
		);

		// API Key field.
		add_settings_field(
			'fotohub_ai_api_key',
			__( 'API Key', 'fotohub-ai' ),
			array( __CLASS__, 'render_api_key_field' ),
			'fotohub-ai-settings',
			'fotohub_ai_general'
		);

		// Default model field.
		add_settings_field(
			'fotohub_ai_default_model',
			__( 'Default Model', 'fotohub-ai' ),
			array( __CLASS__, 'render_model_field' ),
			'fotohub-ai-settings',
			'fotohub_ai_general'
		);

		// Default dimensions.
		add_settings_field(
			'fotohub_ai_dimensions',
			__( 'Default Dimensions', 'fotohub-ai' ),
			array( __CLASS__, 'render_dimensions_field' ),
			'fotohub-ai-settings',
			'fotohub_ai_general'
		);
	}

	/**
	 * Sanitize and encrypt API key before saving.
	 *
	 * @param string $value The submitted API key.
	 */
	public static function sanitize_api_key( string $value ): string {
		$value = sanitize_text_field( $value );

		// If value is masked (unchanged), return the existing encrypted value.
		if ( str_contains( $value, '***' ) ) {
			return get_option( 'fotohub_ai_api_key', '' );
		}

		if ( empty( $value ) ) {
			return '';
		}

		// Store encrypted.
		Fotohub_API::set_api_key( $value );

		// Return the encrypted value (set_api_key already saved it, but WP needs a return).
		return get_option( 'fotohub_ai_api_key', '' );
	}

	/**
	 * Render section description.
	 */
	public static function render_section_description(): void {
		printf(
			'<p>%s <a href="https://fotohub.app/settings/api-keys" target="_blank">%s</a></p>',
			esc_html__( 'Configure your FOTOhub AI API connection.', 'fotohub-ai' ),
			esc_html__( 'Get your API key', 'fotohub-ai' )
		);
	}

	/**
	 * Render API key field.
	 */
	public static function render_api_key_field(): void {
		$has_key = ! empty( Fotohub_API::get_api_key() );
		$display = $has_key ? '***' . substr( Fotohub_API::get_api_key(), -4 ) : '';
		?>
		<input type="password" name="fotohub_ai_api_key" id="fotohub_ai_api_key"
			   value="<?php echo esc_attr( $display ); ?>"
			   class="regular-text" autocomplete="off">
		<button type="button" class="button fotohub-test-connection" id="fotohub-test-connection">
			<?php esc_html_e( 'Test Connection', 'fotohub-ai' ); ?>
		</button>
		<span id="fotohub-connection-status"></span>
		<p class="description">
			<?php esc_html_e( 'Enter your FOTOhub API key. It will be stored encrypted.', 'fotohub-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render model selection field.
	 */
	public static function render_model_field(): void {
		$current = get_option( 'fotohub_ai_default_model', 'seedream-5-0-260128' );
		$models  = array(
			'seedream-5-0-260128' => 'Seedream 5.0 (Recommended)',
			'flux-1-schnell'      => 'Flux 1 Schnell (Fast)',
			'flux-1-dev'          => 'Flux 1 Dev (Quality)',
			'stable-diffusion-xl' => 'Stable Diffusion XL',
			'dall-e-3'            => 'DALL-E 3',
		);
		?>
		<select name="fotohub_ai_default_model" id="fotohub_ai_default_model">
			<?php foreach ( $models as $id => $name ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $current, $id ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Default model for image generation. You can override per-request.', 'fotohub-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render dimensions field.
	 */
	public static function render_dimensions_field(): void {
		$width  = get_option( 'fotohub_ai_default_width', 1024 );
		$height = get_option( 'fotohub_ai_default_height', 1024 );
		?>
		<input type="number" name="fotohub_ai_default_width" value="<?php echo esc_attr( $width ); ?>"
			   min="256" max="2048" step="64" class="small-text"> x
		<input type="number" name="fotohub_ai_default_height" value="<?php echo esc_attr( $height ); ?>"
			   min="256" max="2048" step="64" class="small-text"> px
		<p class="description">
			<?php esc_html_e( 'Default image dimensions (width x height in pixels).', 'fotohub-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		// Only load on our pages and media pages.
		$our_pages = array(
			'settings_page_fotohub-ai-settings',
			'tools_page_fotohub-ai-bulk',
			'toplevel_page_fotohub-ai',
			'fotohub-ai_page_fotohub-ai-stability',
			'fotohub-ai_page_fotohub-ai-analytics',
			'fotohub-ai_page_fotohub-ai-scheduler',
			'fotohub-ai_page_fotohub-ai-bulk',
			'upload.php',
			'post.php',
			'post-new.php',
		);

		if ( ! in_array( $hook, $our_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'fotohub-ai-admin',
			FOTOHUB_AI_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			FOTOHUB_AI_VERSION
		);

		wp_enqueue_script(
			'fotohub-ai-admin',
			FOTOHUB_AI_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			FOTOHUB_AI_VERSION,
			true
		);

		// Load Chart.js on analytics page.
		if ( str_contains( $hook, 'analytics' ) ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);
		}

		// Load wp.media on pages that need it.
		if ( str_contains( $hook, 'stability' ) || str_contains( $hook, 'post' ) ) {
			wp_enqueue_media();
		}

		wp_localize_script( 'fotohub-ai-admin', 'fotohubAI', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'fotohub_ai_nonce' ),
			'i18n'     => array(
				'generating'      => __( 'Generating...', 'fotohub-ai' ),
				'success'         => __( 'Success!', 'fotohub-ai' ),
				'error'           => __( 'Error:', 'fotohub-ai' ),
				'connected'       => __( 'Connected!', 'fotohub-ai' ),
				'disconnected'    => __( 'Connection failed', 'fotohub-ai' ),
				'confirmBulk'     => __( 'Start bulk generation? This will use API credits.', 'fotohub-ai' ),
				'processing'      => __( 'Processing...', 'fotohub-ai' ),
				'complete'        => __( 'Complete!', 'fotohub-ai' ),
				'uploadSuccess'   => __( 'Image added to media library', 'fotohub-ai' ),
				'videoStarted'    => __( 'Video generation started...', 'fotohub-ai' ),
				'videoPolling'    => __( 'Checking video status...', 'fotohub-ai' ),
				'videoComplete'   => __( 'Video ready!', 'fotohub-ai' ),
				'analyzeImage'    => __( 'Analyzing image...', 'fotohub-ai' ),
				'scheduledJob'    => __( 'Job scheduled successfully.', 'fotohub-ai' ),
				'confirmCancel'   => __( 'Cancel this job?', 'fotohub-ai' ),
			),
		) );
	}

	/**
	 * Add settings link to plugin action links.
	 *
	 * @param array $links Existing action links.
	 */
	public static function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=fotohub-ai-settings' ),
			__( 'Settings', 'fotohub-ai' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings_page(): void {
		include FOTOHUB_AI_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Render bulk generation page.
	 */
	public static function render_bulk_page(): void {
		include FOTOHUB_AI_PLUGIN_DIR . 'admin/views/bulk-page.php';
	}

	/**
	 * Render Stability Tools page.
	 */
	public static function render_stability_page(): void {
		include FOTOHUB_AI_PLUGIN_DIR . 'admin/views/stability-tools.php';
	}

	/**
	 * Render Analytics page.
	 */
	public static function render_analytics_page(): void {
		include FOTOHUB_AI_PLUGIN_DIR . 'admin/views/analytics-page.php';
	}

	/**
	 * Render Scheduler page.
	 */
	public static function render_scheduler_page(): void {
		include FOTOHUB_AI_PLUGIN_DIR . 'admin/views/scheduler-page.php';
	}
}
