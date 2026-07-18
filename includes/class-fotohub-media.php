<?php
/**
 * FOTOhub Media Library Integration.
 *
 * Adds AI generation capabilities to WordPress Media Library.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media integration class.
 */
class Fotohub_Media {

	/**
	 * Initialize media hooks.
	 */
	public static function init(): void {
		add_action( 'admin_footer-upload.php', array( __CLASS__, 'render_generate_modal' ) );
		add_action( 'admin_footer-post.php', array( __CLASS__, 'render_generate_modal' ) );
		add_action( 'admin_footer-post-new.php', array( __CLASS__, 'render_generate_modal' ) );
		add_filter( 'media_row_actions', array( __CLASS__, 'add_media_row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( __CLASS__, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_action_notices' ) );
		add_action( 'post-upload-ui', array( __CLASS__, 'add_generate_button' ) );
	}

	/**
	 * Add "Generate with AI" button to upload UI.
	 */
	public static function add_generate_button(): void {
		if ( ! Fotohub_API::get_api_key() ) {
			return;
		}
		?>
		<div class="fotohub-upload-button-wrap">
			<button type="button" class="button button-primary fotohub-open-generate-modal" id="fotohub-generate-btn">
				<span class="dashicons dashicons-art" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Generate with AI', 'fotohub-ai' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Add actions to media row.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    The attachment post.
	 */
	public static function add_media_row_actions( array $actions, WP_Post $post ): array {
		if ( ! Fotohub_API::get_api_key() ) {
			return $actions;
		}

		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $actions;
		}

		$actions['fotohub_remove_bg'] = sprintf(
			'<a href="#" class="fotohub-remove-bg" data-id="%d">%s</a>',
			$post->ID,
			__( 'Remove Background', 'fotohub-ai' )
		);

		$actions['fotohub_upscale'] = sprintf(
			'<a href="#" class="fotohub-upscale" data-id="%d">%s</a>',
			$post->ID,
			__( 'Upscale', 'fotohub-ai' )
		);

		return $actions;
	}

	/**
	 * Add bulk actions to media library.
	 *
	 * @param array $actions Existing bulk actions.
	 */
	public static function add_bulk_actions( array $actions ): array {
		if ( ! Fotohub_API::get_api_key() ) {
			return $actions;
		}

		$actions['fotohub_bulk_remove_bg'] = __( 'Remove Background (FOTOhub)', 'fotohub-ai' );
		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_url Redirect URL after action.
	 * @param string $action       The action being taken.
	 * @param array  $post_ids     Selected post IDs.
	 */
	public static function handle_bulk_actions( string $redirect_url, string $action, array $post_ids ): string {
		if ( 'fotohub_bulk_remove_bg' !== $action ) {
			return $redirect_url;
		}

		$api       = new Fotohub_API();
		$processed = 0;
		$errors    = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! wp_attachment_is_image( $post_id ) ) {
				continue;
			}

			$image_url = wp_get_attachment_url( $post_id );
			if ( ! $image_url ) {
				continue;
			}

			$result = $api->remove_background( $image_url );
			if ( is_wp_error( $result ) ) {
				$errors++;
				continue;
			}

			// Download and save the result as a new attachment.
			$new_id = self::sideload_image( $result['image_url'] ?? $result['url'] ?? '', $post_id );
			if ( is_wp_error( $new_id ) ) {
				$errors++;
				continue;
			}

			$processed++;
		}

		return add_query_arg( array(
			'fotohub_processed' => $processed,
			'fotohub_errors'    => $errors,
		), $redirect_url );
	}

	/**
	 * Display bulk action notices.
	 */
	public static function bulk_action_notices(): void {
		if ( ! empty( $_GET['fotohub_processed'] ) ) {
			$count = absint( $_GET['fotohub_processed'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: number of images processed */
					esc_html( _n(
						'%d image processed with FOTOhub AI.',
						'%d images processed with FOTOhub AI.',
						$count,
						'fotohub-ai'
					) ),
					$count
				)
			);
		}

		if ( ! empty( $_GET['fotohub_errors'] ) ) {
			$errors = absint( $_GET['fotohub_errors'] );
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: number of errors */
					esc_html( _n(
						'%d image failed to process.',
						'%d images failed to process.',
						$errors,
						'fotohub-ai'
					) ),
					$errors
				)
			);
		}
	}

	/**
	 * Render the AI generation modal.
	 */
	public static function render_generate_modal(): void {
		if ( ! Fotohub_API::get_api_key() ) {
			return;
		}
		include FOTOHUB_AI_PLUGIN_DIR . 'admin/views/generate-modal.php';
	}

	/**
	 * Sideload an image from URL into WordPress media library.
	 *
	 * @param string $url       Image URL to download.
	 * @param int    $parent_id Optional parent post ID.
	 * @return int|WP_Error Attachment ID or WP_Error.
	 */
	public static function sideload_image( string $url, int $parent_id = 0 ): int|WP_Error {
		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', __( 'No image URL provided', 'fotohub-ai' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the file.
		$tmp = download_url( $url, 120 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Determine filename.
		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
		if ( empty( $filename ) || ! preg_match( '/\.(jpg|jpeg|png|webp|gif)$/i', $filename ) ) {
			$filename = 'fotohub-ai-' . time() . '.png';
		}

		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp,
		);

		// Upload to media library.
		$attachment_id = media_handle_sideload( $file_array, $parent_id );

		// Clean up temp file if sideload failed.
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
		}

		return $attachment_id;
	}
}
