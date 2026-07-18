<?php
/**
 * FOTOhub Stability AI Tools.
 *
 * Provides all 13 Stability AI image tools accessible from the WordPress Media Library.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stability AI tools class.
 */
class Fotohub_Stability {

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'fotohub-stability-tools';

	/**
	 * Initialize Stability hooks.
	 */
	public static function init(): void {
		// Media Library row actions.
		add_filter( 'media_row_actions', array( __CLASS__, 'add_media_row_actions' ), 10, 2 );

		// Admin menu page.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );

		// AJAX endpoints for each tool.
		add_action( 'wp_ajax_fotohub_stability_tool', array( __CLASS__, 'ajax_process_tool' ) );
	}

	/**
	 * Get all available Stability AI tools with definitions.
	 *
	 * @return array Tool definitions keyed by tool ID.
	 */
	public static function get_available_tools(): array {
		return array(
			'creative-upscale' => array(
				'id'            => 'creative-upscale',
				'label'         => __( 'Creative Upscale', 'fotohub-ai' ),
				'description'   => __( 'Upscale image with AI-enhanced detail guided by a prompt.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'prompt' ),
				'row_label'     => __( 'Stability: Creative Upscale', 'fotohub-ai' ),
			),
			'fast-upscale' => array(
				'id'            => 'fast-upscale',
				'label'         => __( 'Fast Upscale', 'fotohub-ai' ),
				'description'   => __( 'Quick upscale with no prompt needed.', 'fotohub-ai' ),
				'requires'      => array( 'image' ),
				'row_label'     => __( 'Stability: Fast Upscale', 'fotohub-ai' ),
			),
			'conservative-upscale' => array(
				'id'            => 'conservative-upscale',
				'label'         => __( 'Conservative Upscale', 'fotohub-ai' ),
				'description'   => __( 'Upscale with minimal changes to the original.', 'fotohub-ai' ),
				'requires'      => array( 'image' ),
				'row_label'     => __( 'Stability: Conservative Upscale', 'fotohub-ai' ),
			),
			'remove-background' => array(
				'id'            => 'remove-background',
				'label'         => __( 'Remove Background', 'fotohub-ai' ),
				'description'   => __( 'High quality background removal.', 'fotohub-ai' ),
				'requires'      => array( 'image' ),
				'row_label'     => __( 'Stability: Remove BG', 'fotohub-ai' ),
			),
			'erase' => array(
				'id'            => 'erase',
				'label'         => __( 'Erase Object', 'fotohub-ai' ),
				'description'   => __( 'Erase an object from the image using a mask.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'mask' ),
				'row_label'     => __( 'Stability: Erase', 'fotohub-ai' ),
			),
			'inpaint' => array(
				'id'            => 'inpaint',
				'label'         => __( 'Inpaint', 'fotohub-ai' ),
				'description'   => __( 'Fill masked area with AI-generated content guided by a prompt.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'mask', 'prompt' ),
				'row_label'     => __( 'Stability: Inpaint', 'fotohub-ai' ),
			),
			'outpaint' => array(
				'id'            => 'outpaint',
				'label'         => __( 'Outpaint', 'fotohub-ai' ),
				'description'   => __( 'Expand the image beyond its borders.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'padding' ),
				'row_label'     => __( 'Stability: Outpaint', 'fotohub-ai' ),
			),
			'search-and-replace' => array(
				'id'            => 'search-and-replace',
				'label'         => __( 'Search & Replace', 'fotohub-ai' ),
				'description'   => __( 'Find an element in the image and replace it with a prompt.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'prompt' ),
				'row_label'     => __( 'Stability: Search & Replace', 'fotohub-ai' ),
			),
			'search-and-recolor' => array(
				'id'            => 'search-and-recolor',
				'label'         => __( 'Search & Recolor', 'fotohub-ai' ),
				'description'   => __( 'Find an element and change its color.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'prompt' ),
				'row_label'     => __( 'Stability: Recolor', 'fotohub-ai' ),
			),
			'style-transfer' => array(
				'id'            => 'style-transfer',
				'label'         => __( 'Style Transfer', 'fotohub-ai' ),
				'description'   => __( 'Apply a reference style to the image.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'reference' ),
				'row_label'     => __( 'Stability: Style Transfer', 'fotohub-ai' ),
			),
			'control-sketch' => array(
				'id'            => 'control-sketch',
				'label'         => __( 'Control: Sketch', 'fotohub-ai' ),
				'description'   => __( 'Generate an image from a sketch.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'prompt' ),
				'row_label'     => __( 'Stability: From Sketch', 'fotohub-ai' ),
			),
			'control-structure' => array(
				'id'            => 'control-structure',
				'label'         => __( 'Control: Structure', 'fotohub-ai' ),
				'description'   => __( 'Generate an image maintaining the structural composition.', 'fotohub-ai' ),
				'requires'      => array( 'image', 'prompt' ),
				'row_label'     => __( 'Stability: Structure', 'fotohub-ai' ),
			),
			'image-to-video' => array(
				'id'            => 'image-to-video',
				'label'         => __( 'Image to Video', 'fotohub-ai' ),
				'description'   => __( 'Convert a static image into a short video.', 'fotohub-ai' ),
				'requires'      => array( 'image' ),
				'row_label'     => __( 'Stability: Image to Video', 'fotohub-ai' ),
			),
		);
	}

	/**
	 * Add row actions in Media Library for Stability tools.
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

		$tools = self::get_available_tools();

		// Add a subset of the most commonly used tools as row actions.
		$row_tools = array(
			'remove-background',
			'creative-upscale',
			'fast-upscale',
			'erase',
			'inpaint',
			'outpaint',
			'search-and-replace',
			'search-and-recolor',
			'style-transfer',
			'control-sketch',
			'control-structure',
			'conservative-upscale',
			'image-to-video',
		);

		foreach ( $row_tools as $tool_id ) {
			if ( ! isset( $tools[ $tool_id ] ) ) {
				continue;
			}

			$tool       = $tools[ $tool_id ];
			$action_key = 'fotohub_stability_' . str_replace( '-', '_', $tool_id );

			$actions[ $action_key ] = sprintf(
				'<a href="#" class="fotohub-stability-tool" data-id="%d" data-tool="%s" data-requires="%s">%s</a>',
				$post->ID,
				esc_attr( $tool_id ),
				esc_attr( implode( ',', $tool['requires'] ) ),
				esc_html( $tool['row_label'] )
			);
		}

		return $actions;
	}

	/**
	 * Add admin menu page under FOTOhub.
	 */
	public static function add_admin_menu(): void {
		add_submenu_page(
			'fotohub-ai',
			__( 'Stability Tools', 'fotohub-ai' ),
			__( 'Stability Tools', 'fotohub-ai' ),
			'upload_files',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Render the Stability Tools admin page.
	 */
	public static function render_admin_page(): void {
		$tools = self::get_available_tools();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FOTOhub Stability AI Tools', 'fotohub-ai' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Professional image editing tools powered by Stability AI. Select an image from your Media Library and apply any tool below.', 'fotohub-ai' ); ?>
			</p>

			<div class="fotohub-stability-page">
				<!-- Image selector -->
				<div class="fotohub-stability-source">
					<h2><?php esc_html_e( 'Source Image', 'fotohub-ai' ); ?></h2>
					<div class="fotohub-stability-image-preview" id="fotohub-stability-preview">
						<p><?php esc_html_e( 'No image selected', 'fotohub-ai' ); ?></p>
					</div>
					<button type="button" class="button" id="fotohub-stability-select-image">
						<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Select Image', 'fotohub-ai' ); ?>
					</button>
					<input type="hidden" id="fotohub-stability-attachment-id" value="">

					<!-- Reference image (for style-transfer) -->
					<div class="fotohub-stability-reference" id="fotohub-stability-reference-wrap" style="display:none;">
						<h3><?php esc_html_e( 'Reference Image (for Style Transfer)', 'fotohub-ai' ); ?></h3>
						<div class="fotohub-stability-image-preview" id="fotohub-stability-reference-preview">
							<p><?php esc_html_e( 'No reference selected', 'fotohub-ai' ); ?></p>
						</div>
						<button type="button" class="button" id="fotohub-stability-select-reference">
							<?php esc_html_e( 'Select Reference', 'fotohub-ai' ); ?>
						</button>
						<input type="hidden" id="fotohub-stability-reference-id" value="">
					</div>
				</div>

				<!-- Tools grid -->
				<div class="fotohub-stability-tools-grid">
					<h2><?php esc_html_e( 'Available Tools', 'fotohub-ai' ); ?></h2>
					<div class="fotohub-tools-grid">
						<?php foreach ( $tools as $tool_id => $tool ) : ?>
							<div class="fotohub-tool-card" data-tool="<?php echo esc_attr( $tool_id ); ?>"
								 data-requires="<?php echo esc_attr( implode( ',', $tool['requires'] ) ); ?>">
								<h3><?php echo esc_html( $tool['label'] ); ?></h3>
								<p><?php echo esc_html( $tool['description'] ); ?></p>
								<div class="fotohub-tool-fields">
									<?php if ( in_array( 'prompt', $tool['requires'], true ) ) : ?>
										<label>
											<?php esc_html_e( 'Prompt:', 'fotohub-ai' ); ?>
											<input type="text" class="fotohub-tool-prompt regular-text"
												   placeholder="<?php esc_attr_e( 'Describe what you want...', 'fotohub-ai' ); ?>">
										</label>
									<?php endif; ?>

									<?php if ( in_array( 'mask', $tool['requires'], true ) ) : ?>
										<p class="description fotohub-mask-notice">
											<span class="dashicons dashicons-info" style="vertical-align: middle;"></span>
											<?php esc_html_e( 'Mask drawing will open in the editor.', 'fotohub-ai' ); ?>
										</p>
									<?php endif; ?>

									<?php if ( in_array( 'padding', $tool['requires'], true ) ) : ?>
										<div class="fotohub-outpaint-padding">
											<label>
												<?php esc_html_e( 'Left:', 'fotohub-ai' ); ?>
												<input type="number" class="fotohub-padding-left small-text" value="0" min="0" max="1024">
											</label>
											<label>
												<?php esc_html_e( 'Right:', 'fotohub-ai' ); ?>
												<input type="number" class="fotohub-padding-right small-text" value="0" min="0" max="1024">
											</label>
											<label>
												<?php esc_html_e( 'Top:', 'fotohub-ai' ); ?>
												<input type="number" class="fotohub-padding-top small-text" value="0" min="0" max="1024">
											</label>
											<label>
												<?php esc_html_e( 'Bottom:', 'fotohub-ai' ); ?>
												<input type="number" class="fotohub-padding-bottom small-text" value="0" min="0" max="1024">
											</label>
										</div>
									<?php endif; ?>

									<?php if ( in_array( 'reference', $tool['requires'], true ) ) : ?>
										<p class="description">
											<?php esc_html_e( 'Select a reference image above.', 'fotohub-ai' ); ?>
										</p>
									<?php endif; ?>
								</div>
								<button type="button" class="button button-primary fotohub-apply-tool"
										data-tool="<?php echo esc_attr( $tool_id ); ?>">
									<?php esc_html_e( 'Apply', 'fotohub-ai' ); ?>
								</button>
								<span class="fotohub-tool-status spinner"></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Output format selector -->
				<div class="fotohub-stability-options">
					<h2><?php esc_html_e( 'Output Settings', 'fotohub-ai' ); ?></h2>
					<p class="form-field">
						<label for="fotohub-stability-output-format"><?php esc_html_e( 'Output Format:', 'fotohub-ai' ); ?></label>
						<select id="fotohub-stability-output-format">
							<option value="png">PNG</option>
							<option value="webp">WebP</option>
							<option value="jpeg">JPEG</option>
						</select>
					</p>
				</div>

				<!-- Result area -->
				<div class="fotohub-stability-result" id="fotohub-stability-result" style="display:none;">
					<h2><?php esc_html_e( 'Result', 'fotohub-ai' ); ?></h2>
					<div class="fotohub-stability-result-preview"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Process a Stability tool request.
	 */
	public static function ajax_process_tool(): void {
		check_ajax_referer( 'fotohub_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'fotohub-ai' ),
			), 403 );
		}

		$tool_id       = sanitize_text_field( $_POST['tool'] ?? '' );
		$attachment_id = absint( $_POST['attachment_id'] ?? 0 );
		$prompt        = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
		$output_format = sanitize_text_field( $_POST['output_format'] ?? 'png' );
		$reference_id  = absint( $_POST['reference_id'] ?? 0 );
		$mask_data     = sanitize_textarea_field( wp_unslash( $_POST['mask'] ?? '' ) );

		// Validate tool ID.
		$tools = self::get_available_tools();
		if ( ! isset( $tools[ $tool_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tool selected.', 'fotohub-ai' ) ) );
		}

		$tool = $tools[ $tool_id ];

		// Validate attachment.
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid image selected.', 'fotohub-ai' ) ) );
		}

		// Validate requirements.
		if ( in_array( 'prompt', $tool['requires'], true ) && empty( $prompt ) ) {
			wp_send_json_error( array( 'message' => __( 'A prompt is required for this tool.', 'fotohub-ai' ) ) );
		}

		if ( in_array( 'mask', $tool['requires'], true ) && empty( $mask_data ) ) {
			wp_send_json_error( array(
				'message'      => __( 'A mask is required for this tool.', 'fotohub-ai' ),
				'needs_mask'   => true,
				'tool'         => $tool_id,
				'attachment_id' => $attachment_id,
			) );
		}

		if ( in_array( 'reference', $tool['requires'], true ) && ! $reference_id ) {
			wp_send_json_error( array( 'message' => __( 'A reference image is required for this tool.', 'fotohub-ai' ) ) );
		}

		// Get image as base64.
		$image_base64 = self::get_image_base64( $attachment_id );
		if ( empty( $image_base64 ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to read image file.', 'fotohub-ai' ) ) );
		}

		// Build request body.
		$body = array(
			'image'         => $image_base64,
			'output_format' => $output_format,
		);

		if ( ! empty( $prompt ) ) {
			$body['prompt'] = $prompt;
		}

		if ( ! empty( $mask_data ) ) {
			$body['mask'] = $mask_data;
		}

		if ( $reference_id ) {
			$reference_base64 = self::get_image_base64( $reference_id );
			if ( ! empty( $reference_base64 ) ) {
				$body['reference'] = $reference_base64;
			}
		}

		// Handle outpaint padding.
		if ( in_array( 'padding', $tool['requires'], true ) ) {
			$body['padding'] = array(
				'left'   => absint( $_POST['padding_left'] ?? 128 ),
				'right'  => absint( $_POST['padding_right'] ?? 128 ),
				'top'    => absint( $_POST['padding_top'] ?? 0 ),
				'bottom' => absint( $_POST['padding_bottom'] ?? 0 ),
			);
		}

		// Send request to Stability API.
		$result = self::call_stability_api( $tool_id, $body );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Handle the response: save result as a new attachment.
		$result_image = $result['image'] ?? $result['output'] ?? $result['url'] ?? '';

		if ( empty( $result_image ) ) {
			wp_send_json_error( array( 'message' => __( 'No output received from API.', 'fotohub-ai' ) ) );
		}

		// If the result is a URL, sideload it.
		if ( filter_var( $result_image, FILTER_VALIDATE_URL ) ) {
			$new_id = Fotohub_Media::sideload_image( $result_image, wp_get_post_parent_id( $attachment_id ) );
		} else {
			// Result is base64 data; decode and save.
			$new_id = self::save_base64_image( $result_image, $attachment_id, $tool_id, $output_format );
		}

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ) );
		}

		// Mark with metadata.
		update_post_meta( $new_id, '_fotohub_stability_tool', $tool_id );
		update_post_meta( $new_id, '_fotohub_stability_source', $attachment_id );

		wp_send_json_success( array(
			'id'        => $new_id,
			'url'       => wp_get_attachment_url( $new_id ),
			'thumbnail' => wp_get_attachment_image_url( $new_id, 'medium' ),
			'message'   => sprintf(
				/* translators: %s: tool label */
				__( '%s applied successfully.', 'fotohub-ai' ),
				$tool['label']
			),
		) );
	}

	/**
	 * Get an attachment image as a base64-encoded string.
	 *
	 * @param int $attachment_id The WordPress attachment ID.
	 * @return string Base64-encoded image data, or empty string on failure.
	 */
	public static function get_image_base64( int $attachment_id ): string {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return '';
		}

		$contents = file_get_contents( $file_path );
		if ( false === $contents ) {
			return '';
		}

		return base64_encode( $contents );
	}

	/**
	 * Call the Stability API endpoint.
	 *
	 * @param string $tool_id The tool identifier.
	 * @param array  $body    The request body.
	 * @return array|WP_Error API response or WP_Error.
	 */
	private static function call_stability_api( string $tool_id, array $body ): array|WP_Error {
		$url     = FOTOHUB_AI_API_BASE . '/stability/' . urlencode( $tool_id );
		$headers = array(
			'Authorization' => 'Bearer ' . Fotohub_API::get_api_key(),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'User-Agent'    => 'FOTOhub-WordPress/' . FOTOHUB_AI_VERSION,
		);

		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'timeout' => 180,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$message = $data['error'] ?? $data['message'] ?? __( 'Stability API error', 'fotohub-ai' );
			return new WP_Error(
				'fotohub_stability_error',
				sprintf( '[%d] %s', $code, $message ),
				array( 'status' => $code )
			);
		}

		if ( null === $data ) {
			return new WP_Error(
				'fotohub_stability_parse_error',
				__( 'Failed to parse Stability API response', 'fotohub-ai' )
			);
		}

		return $data;
	}

	/**
	 * Save a base64-encoded image as a WordPress attachment.
	 *
	 * @param string $base64_data   The base64-encoded image data.
	 * @param int    $source_id     The source attachment ID (for parent reference).
	 * @param string $tool_id       The tool that produced this result.
	 * @param string $output_format The output format (png, webp, jpeg).
	 * @return int|WP_Error Attachment ID or WP_Error.
	 */
	private static function save_base64_image( string $base64_data, int $source_id, string $tool_id, string $output_format = 'png' ): int|WP_Error {
		$decoded = base64_decode( $base64_data );
		if ( false === $decoded ) {
			return new WP_Error( 'decode_error', __( 'Failed to decode base64 image data.', 'fotohub-ai' ) );
		}

		// Determine file extension and mime type.
		$ext_map = array(
			'png'  => array( 'ext' => 'png', 'mime' => 'image/png' ),
			'webp' => array( 'ext' => 'webp', 'mime' => 'image/webp' ),
			'jpeg' => array( 'ext' => 'jpg', 'mime' => 'image/jpeg' ),
			'jpg'  => array( 'ext' => 'jpg', 'mime' => 'image/jpeg' ),
		);

		$format_info = $ext_map[ $output_format ] ?? $ext_map['png'];
		$filename    = sprintf( 'fotohub-stability-%s-%d.%s', $tool_id, time(), $format_info['ext'] );

		// Write to temp file.
		$upload_dir = wp_upload_dir();
		$tmp_path   = $upload_dir['path'] . '/' . $filename;

		if ( false === file_put_contents( $tmp_path, $decoded ) ) {
			return new WP_Error( 'write_error', __( 'Failed to write image file.', 'fotohub-ai' ) );
		}

		$parent_id = wp_get_post_parent_id( $source_id ) ?: 0;

		$attachment = array(
			'post_mime_type' => $format_info['mime'],
			'post_title'    => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'  => '',
			'post_status'   => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $tmp_path, $parent_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp_path );
			return $attachment_id;
		}

		// Generate attachment metadata.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $tmp_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}
}
