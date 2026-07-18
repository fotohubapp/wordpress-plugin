<?php
/**
 * FOTOhub Video Generation.
 *
 * Provides video generation capabilities for WooCommerce products including
 * product videos, 360-degree turntables, and lifestyle videos.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Video generation class.
 */
class Fotohub_Video {

	/**
	 * Supported video generation models.
	 */
	private const SUPPORTED_MODELS = array(
		'veo-2'    => 'Veo 2 (Google)',
		'veo-3'    => 'Veo 3 (Google)',
		'wan'      => 'Wan (Alibaba)',
		'kling'    => 'Kling (Kuaishou)',
		'hailuo'   => 'Hailuo (MiniMax)',
		'seedance' => 'Seedance (ByteDance)',
		'sora-2'   => 'Sora 2 (OpenAI)',
	);

	/**
	 * Default video model.
	 */
	private const DEFAULT_MODEL = 'veo-2';

	/**
	 * Cron hook name for polling pending video jobs.
	 */
	private const CRON_HOOK = 'fotohub_video_poll_jobs';

	/**
	 * Post meta key for storing video job IDs.
	 */
	private const META_VIDEO_JOBS = '_fotohub_video_jobs';

	/**
	 * Initialize video hooks.
	 */
	public static function init(): void {
		// WooCommerce product data tab.
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_data_panel' ) );

		// AJAX endpoints.
		add_action( 'wp_ajax_fotohub_generate_video', array( __CLASS__, 'ajax_generate_video' ) );
		add_action( 'wp_ajax_fotohub_poll_video_status', array( __CLASS__, 'ajax_poll_video_status' ) );
		add_action( 'wp_ajax_fotohub_generate_turntable', array( __CLASS__, 'ajax_generate_turntable' ) );
		add_action( 'wp_ajax_fotohub_generate_lifestyle_video', array( __CLASS__, 'ajax_generate_lifestyle_video' ) );

		// WP-Cron for checking pending video jobs.
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_poll_pending_jobs' ) );

		// Schedule cron if not already scheduled.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'every_two_minutes', self::CRON_HOOK );
		}

		// Register custom cron interval.
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );

		// Shortcode for displaying product videos.
		add_shortcode( 'fotohub_product_video', array( __CLASS__, 'shortcode_product_video' ) );
	}

	/**
	 * Add a custom cron interval for video polling.
	 *
	 * @param array $schedules Existing cron schedules.
	 */
	public static function add_cron_interval( array $schedules ): array {
		$schedules['every_two_minutes'] = array(
			'interval' => 120,
			'display'  => __( 'Every Two Minutes', 'fotohub-ai' ),
		);
		return $schedules;
	}

	/**
	 * Add Video tab to WooCommerce product data.
	 *
	 * @param array $tabs Existing product data tabs.
	 */
	public static function add_product_data_tab( array $tabs ): array {
		$tabs['fotohub_video'] = array(
			'label'    => __( 'Video', 'fotohub-ai' ),
			'target'   => 'fotohub_video_product_data',
			'class'    => array(),
			'priority' => 81,
		);
		return $tabs;
	}

	/**
	 * Render the Video product data panel.
	 */
	public static function render_product_data_panel(): void {
		global $post;

		$pending_jobs = get_post_meta( $post->ID, self::META_VIDEO_JOBS, true ) ?: array();
		$videos       = self::get_product_videos( $post->ID );
		?>
		<div id="fotohub_video_product_data" class="panel woocommerce_options_panel">
			<div class="options_group fotohub-video-panel">
				<p class="form-field">
					<label><?php esc_html_e( 'Generate Product Video', 'fotohub-ai' ); ?></label>
					<span class="description">
						<?php esc_html_e( 'Use AI to generate product videos, 360-degree turntables, and lifestyle videos.', 'fotohub-ai' ); ?>
					</span>
				</p>

				<p class="form-field">
					<label for="fotohub_video_type"><?php esc_html_e( 'Video Type', 'fotohub-ai' ); ?></label>
					<select id="fotohub_video_type" name="fotohub_video_type">
						<option value="product"><?php esc_html_e( 'Product Video', 'fotohub-ai' ); ?></option>
						<option value="turntable"><?php esc_html_e( '360° Turntable', 'fotohub-ai' ); ?></option>
						<option value="lifestyle"><?php esc_html_e( 'Lifestyle Video', 'fotohub-ai' ); ?></option>
					</select>
				</p>

				<p class="form-field">
					<label for="fotohub_video_model"><?php esc_html_e( 'Model', 'fotohub-ai' ); ?></label>
					<select id="fotohub_video_model" name="fotohub_video_model">
						<?php foreach ( self::SUPPORTED_MODELS as $id => $name ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, self::DEFAULT_MODEL ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<p class="form-field">
					<label for="fotohub_video_prompt"><?php esc_html_e( 'Custom Prompt (optional)', 'fotohub-ai' ); ?></label>
					<textarea id="fotohub_video_prompt" name="fotohub_video_prompt" rows="3"
							  class="large-text" placeholder="<?php esc_attr_e( 'Leave empty to auto-generate from product data', 'fotohub-ai' ); ?>"></textarea>
				</p>

				<p class="form-field">
					<label for="fotohub_video_duration"><?php esc_html_e( 'Duration (seconds)', 'fotohub-ai' ); ?></label>
					<select id="fotohub_video_duration" name="fotohub_video_duration">
						<option value="4">4s</option>
						<option value="6" selected>6s</option>
						<option value="8">8s</option>
						<option value="10">10s</option>
					</select>
				</p>

				<p class="form-field">
					<label for="fotohub_video_aspect_ratio"><?php esc_html_e( 'Aspect Ratio', 'fotohub-ai' ); ?></label>
					<select id="fotohub_video_aspect_ratio" name="fotohub_video_aspect_ratio">
						<option value="16:9"><?php esc_html_e( '16:9 (Landscape)', 'fotohub-ai' ); ?></option>
						<option value="9:16"><?php esc_html_e( '9:16 (Portrait)', 'fotohub-ai' ); ?></option>
						<option value="1:1" selected><?php esc_html_e( '1:1 (Square)', 'fotohub-ai' ); ?></option>
					</select>
				</p>

				<p class="form-field">
					<button type="button" class="button button-primary fotohub-generate-video"
							data-product-id="<?php echo esc_attr( $post->ID ); ?>">
						<span class="dashicons dashicons-video-alt3" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Generate Product Video', 'fotohub-ai' ); ?>
					</button>
					<span class="fotohub-video-status"></span>
				</p>

				<?php if ( ! empty( $pending_jobs ) ) : ?>
					<div class="fotohub-video-pending">
						<p><strong><?php esc_html_e( 'Pending Video Jobs:', 'fotohub-ai' ); ?></strong></p>
						<ul>
							<?php foreach ( $pending_jobs as $job ) : ?>
								<li>
									<?php echo esc_html( $job['model'] ?? 'unknown' ); ?> &mdash;
									<span class="fotohub-job-status" data-job-id="<?php echo esc_attr( $job['job_id'] ); ?>">
										<?php esc_html_e( 'Processing...', 'fotohub-ai' ); ?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $videos ) ) : ?>
					<div class="fotohub-video-gallery">
						<p><strong><?php esc_html_e( 'Product Videos:', 'fotohub-ai' ); ?></strong></p>
						<div class="fotohub-video-grid">
							<?php foreach ( $videos as $video_id ) : ?>
								<div class="fotohub-video-item">
									<video controls preload="metadata" width="250">
										<source src="<?php echo esc_url( wp_get_attachment_url( $video_id ) ); ?>" type="video/mp4">
									</video>
									<p class="description"><?php echo esc_html( get_the_title( $video_id ) ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Generate a product video.
	 */
	public static function ajax_generate_video(): void {
		self::verify_request();

		$product_id   = absint( $_POST['product_id'] ?? 0 );
		$model        = sanitize_text_field( $_POST['model'] ?? self::DEFAULT_MODEL );
		$duration     = absint( $_POST['duration'] ?? 6 );
		$aspect_ratio = sanitize_text_field( $_POST['aspect_ratio'] ?? '1:1' );
		$prompt       = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'fotohub-ai' ) ) );
		}

		if ( ! array_key_exists( $model, self::SUPPORTED_MODELS ) ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported video model.', 'fotohub-ai' ) ) );
		}

		// Build prompt from product data if not provided.
		if ( empty( $prompt ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$prompt = self::build_video_prompt( $product, 'product' );
			}
		}

		if ( empty( $prompt ) ) {
			wp_send_json_error( array( 'message' => __( 'Prompt is required.', 'fotohub-ai' ) ) );
		}

		// Get the product image URL for image-to-video generation.
		$image_url = '';
		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product  = wc_get_product( $product_id );
			$image_id = $product ? $product->get_image_id() : 0;
			if ( $image_id ) {
				$image_url = wp_get_attachment_url( $image_id );
			}
		}

		$result = self::request_video_generation( $prompt, $model, $duration, $aspect_ratio, $image_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$job_id = $result['job_id'] ?? $result['id'] ?? '';
		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No job ID returned from API.', 'fotohub-ai' ) ) );
		}

		// Store job in post meta.
		self::add_pending_job( $product_id, $job_id, $model, $prompt );

		wp_send_json_success( array(
			'job_id'  => $job_id,
			'message' => __( 'Video generation started. It will appear in the gallery when complete.', 'fotohub-ai' ),
		) );
	}

	/**
	 * AJAX handler: Generate a 360-degree turntable video.
	 */
	public static function ajax_generate_turntable(): void {
		self::verify_request();

		$product_id   = absint( $_POST['product_id'] ?? 0 );
		$model        = sanitize_text_field( $_POST['model'] ?? self::DEFAULT_MODEL );
		$duration     = absint( $_POST['duration'] ?? 6 );
		$aspect_ratio = sanitize_text_field( $_POST['aspect_ratio'] ?? '1:1' );

		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product or WooCommerce not active.', 'fotohub-ai' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'fotohub-ai' ) ) );
		}

		$prompt = self::build_video_prompt( $product, 'turntable' );

		// Use product image for image-to-video.
		$image_url = '';
		$image_id  = $product->get_image_id();
		if ( $image_id ) {
			$image_url = wp_get_attachment_url( $image_id );
		}

		$result = self::request_video_generation( $prompt, $model, $duration, $aspect_ratio, $image_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$job_id = $result['job_id'] ?? $result['id'] ?? '';
		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No job ID returned from API.', 'fotohub-ai' ) ) );
		}

		self::add_pending_job( $product_id, $job_id, $model, $prompt );

		wp_send_json_success( array(
			'job_id'  => $job_id,
			'message' => __( '360° turntable generation started.', 'fotohub-ai' ),
		) );
	}

	/**
	 * AJAX handler: Generate a lifestyle video from product data.
	 */
	public static function ajax_generate_lifestyle_video(): void {
		self::verify_request();

		$product_id   = absint( $_POST['product_id'] ?? 0 );
		$model        = sanitize_text_field( $_POST['model'] ?? self::DEFAULT_MODEL );
		$duration     = absint( $_POST['duration'] ?? 8 );
		$aspect_ratio = sanitize_text_field( $_POST['aspect_ratio'] ?? '16:9' );

		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product or WooCommerce not active.', 'fotohub-ai' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'fotohub-ai' ) ) );
		}

		$prompt = self::build_video_prompt( $product, 'lifestyle' );

		// Use product image for image-to-video.
		$image_url = '';
		$image_id  = $product->get_image_id();
		if ( $image_id ) {
			$image_url = wp_get_attachment_url( $image_id );
		}

		$result = self::request_video_generation( $prompt, $model, $duration, $aspect_ratio, $image_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$job_id = $result['job_id'] ?? $result['id'] ?? '';
		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No job ID returned from API.', 'fotohub-ai' ) ) );
		}

		self::add_pending_job( $product_id, $job_id, $model, $prompt );

		wp_send_json_success( array(
			'job_id'  => $job_id,
			'message' => __( 'Lifestyle video generation started.', 'fotohub-ai' ),
		) );
	}

	/**
	 * AJAX handler: Poll video job status.
	 */
	public static function ajax_poll_video_status(): void {
		self::verify_request();

		$job_id     = sanitize_text_field( $_POST['job_id'] ?? '' );
		$product_id = absint( $_POST['product_id'] ?? 0 );

		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No job ID provided.', 'fotohub-ai' ) ) );
		}

		$api      = new Fotohub_API();
		$response = self::check_job_status( $api, $job_id );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$status = $response['status'] ?? 'unknown';

		if ( 'completed' === $status || 'succeeded' === $status ) {
			$video_url = $response['video_url'] ?? $response['output']['video_url'] ?? $response['result']['url'] ?? '';

			if ( ! empty( $video_url ) && $product_id ) {
				$attachment_id = self::sideload_video( $video_url, $product_id );
				if ( ! is_wp_error( $attachment_id ) ) {
					self::remove_pending_job( $product_id, $job_id );
					wp_send_json_success( array(
						'status'        => 'completed',
						'attachment_id' => $attachment_id,
						'url'           => wp_get_attachment_url( $attachment_id ),
						'message'       => __( 'Video generation complete.', 'fotohub-ai' ),
					) );
				}
			}

			self::remove_pending_job( $product_id, $job_id );
			wp_send_json_success( array(
				'status'  => 'completed',
				'url'     => $video_url,
				'message' => __( 'Video generation complete.', 'fotohub-ai' ),
			) );
		}

		if ( 'failed' === $status || 'error' === $status ) {
			self::remove_pending_job( $product_id, $job_id );
			$error_msg = $response['error'] ?? $response['message'] ?? __( 'Video generation failed.', 'fotohub-ai' );
			wp_send_json_error( array(
				'status'  => 'failed',
				'message' => $error_msg,
			) );
		}

		// Still processing.
		$progress = $response['progress'] ?? null;
		wp_send_json_success( array(
			'status'   => 'processing',
			'progress' => $progress,
			'message'  => __( 'Video is still being generated...', 'fotohub-ai' ),
		) );
	}

	/**
	 * WP-Cron: Poll all pending video jobs across all products.
	 */
	public static function cron_poll_pending_jobs(): void {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
				self::META_VIDEO_JOBS
			)
		);

		if ( empty( $results ) ) {
			return;
		}

		$api = new Fotohub_API();
		if ( ! $api->is_configured() ) {
			return;
		}

		foreach ( $results as $row ) {
			$product_id = (int) $row->post_id;
			$jobs       = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $jobs ) || empty( $jobs ) ) {
				continue;
			}

			foreach ( $jobs as $index => $job ) {
				$job_id = $job['job_id'] ?? '';
				if ( empty( $job_id ) ) {
					unset( $jobs[ $index ] );
					continue;
				}

				$response = self::check_job_status( $api, $job_id );
				if ( is_wp_error( $response ) ) {
					continue;
				}

				$status = $response['status'] ?? 'unknown';

				if ( 'completed' === $status || 'succeeded' === $status ) {
					$video_url = $response['video_url'] ?? $response['output']['video_url'] ?? $response['result']['url'] ?? '';

					if ( ! empty( $video_url ) ) {
						self::sideload_video( $video_url, $product_id );
					}

					unset( $jobs[ $index ] );
				} elseif ( 'failed' === $status || 'error' === $status ) {
					unset( $jobs[ $index ] );
				}
			}

			// Update remaining jobs.
			$jobs = array_values( $jobs );
			if ( empty( $jobs ) ) {
				delete_post_meta( $product_id, self::META_VIDEO_JOBS );
			} else {
				update_post_meta( $product_id, self::META_VIDEO_JOBS, $jobs );
			}
		}
	}

	/**
	 * Shortcode: Display product videos.
	 *
	 * Usage: [fotohub_product_video] or [fotohub_product_video id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public static function shortcode_product_video( $atts ): string {
		$atts = shortcode_atts( array(
			'id'       => 0,
			'autoplay' => 'false',
			'loop'     => 'true',
			'muted'    => 'true',
			'controls' => 'true',
			'width'    => '100%',
		), $atts, 'fotohub_product_video' );

		$product_id = absint( $atts['id'] );
		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}

		if ( ! $product_id ) {
			return '';
		}

		$videos = self::get_product_videos( $product_id );
		if ( empty( $videos ) ) {
			return '';
		}

		$autoplay = 'true' === $atts['autoplay'] ? ' autoplay' : '';
		$loop     = 'true' === $atts['loop'] ? ' loop' : '';
		$muted    = 'true' === $atts['muted'] ? ' muted' : '';
		$controls = 'true' === $atts['controls'] ? ' controls' : '';
		$width    = esc_attr( $atts['width'] );

		$output = '<div class="fotohub-product-videos">';
		foreach ( $videos as $video_id ) {
			$url = wp_get_attachment_url( $video_id );
			if ( ! $url ) {
				continue;
			}
			$output .= sprintf(
				'<div class="fotohub-product-video-item"><video%s%s%s%s preload="metadata" style="width:%s;max-width:100%%;">'
				. '<source src="%s" type="video/mp4">'
				. '%s</video></div>',
				$controls,
				$autoplay,
				$loop,
				$muted,
				$width,
				esc_url( $url ),
				esc_html__( 'Your browser does not support the video tag.', 'fotohub-ai' )
			);
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Request video generation from the FOTOhub API.
	 *
	 * @param string $prompt       The generation prompt.
	 * @param string $model        The model ID.
	 * @param int    $duration     Video duration in seconds.
	 * @param string $aspect_ratio Aspect ratio (e.g., "16:9").
	 * @param string $image_url    Optional source image URL for image-to-video.
	 * @param string $resolution   Optional resolution (e.g., "1080p").
	 * @return array|WP_Error API response or WP_Error.
	 */
	private static function request_video_generation(
		string $prompt,
		string $model = 'veo-2',
		int $duration = 6,
		string $aspect_ratio = '1:1',
		string $image_url = '',
		string $resolution = '1080p'
	): array|WP_Error {
		$api  = new Fotohub_API();
		$body = array(
			'prompt'       => $prompt,
			'model'        => $model,
			'duration'     => $duration,
			'aspect_ratio' => $aspect_ratio,
			'resolution'   => $resolution,
		);

		if ( ! empty( $image_url ) ) {
			$body['image_url'] = $image_url;
		}

		// Use reflection to access the private post method, or build a direct request.
		$url     = FOTOHUB_AI_API_BASE . '/v1/ai/generate/video';
		$headers = array(
			'Authorization' => 'Bearer ' . Fotohub_API::get_api_key(),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'User-Agent'    => 'FOTOhub-WordPress/' . FOTOHUB_AI_VERSION,
		);

		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'timeout' => 120,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$message = $data['error'] ?? $data['message'] ?? __( 'Video generation API error', 'fotohub-ai' );
			return new WP_Error( 'fotohub_video_api_error', sprintf( '[%d] %s', $code, $message ) );
		}

		if ( null === $data ) {
			return new WP_Error( 'fotohub_video_parse_error', __( 'Failed to parse video API response', 'fotohub-ai' ) );
		}

		return $data;
	}

	/**
	 * Check the status of a video generation job.
	 *
	 * @param Fotohub_API $api    The API client instance.
	 * @param string      $job_id The job ID to check.
	 * @return array|WP_Error Status response or WP_Error.
	 */
	private static function check_job_status( Fotohub_API $api, string $job_id ): array|WP_Error {
		$url     = FOTOHUB_AI_API_BASE . '/v1/ai/generate/video/status/' . urlencode( $job_id );
		$headers = array(
			'Authorization' => 'Bearer ' . Fotohub_API::get_api_key(),
			'Accept'        => 'application/json',
			'User-Agent'    => 'FOTOhub-WordPress/' . FOTOHUB_AI_VERSION,
		);

		$response = wp_remote_get( $url, array(
			'headers' => $headers,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$message = $data['error'] ?? $data['message'] ?? __( 'Status check failed', 'fotohub-ai' );
			return new WP_Error( 'fotohub_video_status_error', sprintf( '[%d] %s', $code, $message ) );
		}

		if ( null === $data ) {
			return new WP_Error( 'fotohub_video_parse_error', __( 'Failed to parse status response', 'fotohub-ai' ) );
		}

		return $data;
	}

	/**
	 * Sideload a video from URL into WordPress media library.
	 *
	 * @param string $url        Video URL to download.
	 * @param int    $parent_id  Parent post ID (product).
	 * @return int|WP_Error Attachment ID or WP_Error.
	 */
	private static function sideload_video( string $url, int $parent_id = 0 ): int|WP_Error {
		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', __( 'No video URL provided', 'fotohub-ai' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the video file.
		$tmp = download_url( $url, 300 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Determine filename.
		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
		if ( empty( $filename ) || ! preg_match( '/\.(mp4|webm|mov)$/i', $filename ) ) {
			$filename = 'fotohub-video-' . time() . '.mp4';
		}

		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp,
		);

		// Upload to media library.
		$attachment_id = media_handle_sideload( $file_array, $parent_id );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return $attachment_id;
		}

		// Set mime type explicitly.
		wp_update_post( array(
			'ID'             => $attachment_id,
			'post_mime_type' => 'video/mp4',
		) );

		// Mark as a FOTOhub-generated video.
		update_post_meta( $attachment_id, '_fotohub_generated_video', true );
		update_post_meta( $attachment_id, '_fotohub_video_parent', $parent_id );

		return $attachment_id;
	}

	/**
	 * Add a pending video job to a product's meta.
	 *
	 * @param int    $product_id The product ID.
	 * @param string $job_id     The API job ID.
	 * @param string $model      The model used.
	 * @param string $prompt     The prompt used.
	 */
	private static function add_pending_job( int $product_id, string $job_id, string $model, string $prompt ): void {
		$jobs = get_post_meta( $product_id, self::META_VIDEO_JOBS, true ) ?: array();

		$jobs[] = array(
			'job_id'     => $job_id,
			'model'      => $model,
			'prompt'     => $prompt,
			'created_at' => current_time( 'mysql' ),
		);

		update_post_meta( $product_id, self::META_VIDEO_JOBS, $jobs );
	}

	/**
	 * Remove a completed/failed job from a product's meta.
	 *
	 * @param int    $product_id The product ID.
	 * @param string $job_id     The job ID to remove.
	 */
	private static function remove_pending_job( int $product_id, string $job_id ): void {
		if ( ! $product_id ) {
			return;
		}

		$jobs = get_post_meta( $product_id, self::META_VIDEO_JOBS, true ) ?: array();
		$jobs = array_filter( $jobs, fn( $job ) => ( $job['job_id'] ?? '' ) !== $job_id );
		$jobs = array_values( $jobs );

		if ( empty( $jobs ) ) {
			delete_post_meta( $product_id, self::META_VIDEO_JOBS );
		} else {
			update_post_meta( $product_id, self::META_VIDEO_JOBS, $jobs );
		}
	}

	/**
	 * Get all video attachments for a product.
	 *
	 * @param int $product_id The product ID.
	 * @return array Array of attachment IDs.
	 */
	private static function get_product_videos( int $product_id ): array {
		$args = array(
			'post_parent'    => $product_id,
			'post_type'      => 'attachment',
			'post_mime_type' => 'video/mp4',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'   => '_fotohub_generated_video',
					'value' => '1',
				),
			),
		);

		return get_posts( $args );
	}

	/**
	 * Build a video generation prompt from a WooCommerce product.
	 *
	 * @param WC_Product $product The product object.
	 * @param string     $type    Video type: "product", "turntable", or "lifestyle".
	 */
	private static function build_video_prompt( $product, string $type = 'product' ): string {
		$name        = $product->get_name();
		$description = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
		$description = wp_trim_words( $description, 20, '' );

		$prompt = match ( $type ) {
			'turntable' => sprintf(
				'360-degree rotating turntable video of %s on a clean white background, smooth continuous rotation showing all angles, studio lighting, product showcase.',
				$name
			),
			'lifestyle' => sprintf(
				'Cinematic lifestyle video showcasing %s in a natural, aspirational setting. Smooth camera movement, warm lighting, lifestyle product video.',
				$name
			),
			default => sprintf(
				'Professional product video of %s, clean presentation, subtle movement, studio lighting, commercial quality.',
				$name
			),
		};

		if ( ! empty( $description ) ) {
			$prompt .= ' ' . $description;
		}

		return $prompt;
	}

	/**
	 * Verify AJAX request nonce and capabilities.
	 */
	private static function verify_request(): void {
		check_ajax_referer( 'fotohub_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'fotohub-ai' ),
			), 403 );
		}
	}

	/**
	 * Get supported video models.
	 *
	 * @return array Associative array of model_id => label.
	 */
	public static function get_supported_models(): array {
		return self::SUPPORTED_MODELS;
	}

	/**
	 * Clean up cron on plugin deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
