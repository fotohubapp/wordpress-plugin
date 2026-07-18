<?php
/**
 * FOTOhub AJAX Handlers.
 *
 * Handles all AJAX requests for the FOTOhub AI plugin.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler class.
 */
class Fotohub_Ajax {

	/**
	 * Initialize AJAX hooks.
	 */
	public static function init(): void {
		$actions = array(
			'fotohub_generate_image',
			'fotohub_remove_bg',
			'fotohub_upscale',
			'fotohub_test_connection',
			'fotohub_bulk_generate',
			'fotohub_generate_product_photos',
			'fotohub_generate_video',
			'fotohub_check_video_status',
			'fotohub_stability_tool',
			'fotohub_generate_copy',
			'fotohub_analyze_image',
			'fotohub_bulk_alt_text',
			'fotohub_schedule_job',
			'fotohub_get_analytics',
			'fotohub_estimate_cost',
			'fotohub_export_csv',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $action ) );
		}
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
	 * Generate an image via AJAX.
	 */
	public static function fotohub_generate_image(): void {
		self::verify_request();

		$prompt = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
		if ( empty( $prompt ) ) {
			wp_send_json_error( array( 'message' => __( 'Prompt is required.', 'fotohub-ai' ) ) );
		}

		$options = array(
			'model'      => sanitize_text_field( $_POST['model'] ?? '' ),
			'width'      => absint( $_POST['width'] ?? 0 ),
			'height'     => absint( $_POST['height'] ?? 0 ),
			'num_images' => absint( $_POST['num_images'] ?? 1 ),
		);

		// Remove empty options to use defaults.
		$options = array_filter( $options );

		$api    = new Fotohub_API();
		$result = $api->generate_image( $prompt, $options );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Sideload generated images into media library.
		$attachments = array();
		$images      = $result['images'] ?? array( $result );

		foreach ( $images as $image ) {
			$image_url = $image['url'] ?? $image['image_url'] ?? '';
			if ( empty( $image_url ) ) {
				continue;
			}

			$attachment_id = Fotohub_Media::sideload_image( $image_url );
			if ( ! is_wp_error( $attachment_id ) ) {
				$attachments[] = array(
					'id'        => $attachment_id,
					'url'       => wp_get_attachment_url( $attachment_id ),
					'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
					'title'     => get_the_title( $attachment_id ),
				);
			}
		}

		if ( empty( $attachments ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save generated images.', 'fotohub-ai' ) ) );
		}

		wp_send_json_success( array(
			'images'  => $attachments,
			'message' => sprintf(
				/* translators: %d: number of images */
				_n( '%d image generated successfully.', '%d images generated successfully.', count( $attachments ), 'fotohub-ai' ),
				count( $attachments )
			),
		) );
	}

	/**
	 * Remove background via AJAX.
	 */
	public static function fotohub_remove_bg(): void {
		self::verify_request();

		$attachment_id = absint( $_POST['attachment_id'] ?? 0 );
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'No image selected.', 'fotohub-ai' ) ) );
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			wp_send_json_error( array( 'message' => __( 'Could not get image URL.', 'fotohub-ai' ) ) );
		}

		$api    = new Fotohub_API();
		$result = $api->remove_background( $image_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$result_url    = $result['image_url'] ?? $result['url'] ?? '';
		$new_attach_id = Fotohub_Media::sideload_image( $result_url, wp_get_post_parent_id( $attachment_id ) );

		if ( is_wp_error( $new_attach_id ) ) {
			wp_send_json_error( array( 'message' => $new_attach_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'id'        => $new_attach_id,
			'url'       => wp_get_attachment_url( $new_attach_id ),
			'thumbnail' => wp_get_attachment_image_url( $new_attach_id, 'thumbnail' ),
			'message'   => __( 'Background removed successfully.', 'fotohub-ai' ),
		) );
	}

	/**
	 * Upscale image via AJAX.
	 */
	public static function fotohub_upscale(): void {
		self::verify_request();

		$attachment_id = absint( $_POST['attachment_id'] ?? 0 );
		$scale         = absint( $_POST['scale'] ?? 2 );

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'No image selected.', 'fotohub-ai' ) ) );
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			wp_send_json_error( array( 'message' => __( 'Could not get image URL.', 'fotohub-ai' ) ) );
		}

		$api    = new Fotohub_API();
		$result = $api->upscale_image( $image_url, $scale );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$result_url    = $result['image_url'] ?? $result['url'] ?? '';
		$new_attach_id = Fotohub_Media::sideload_image( $result_url, wp_get_post_parent_id( $attachment_id ) );

		if ( is_wp_error( $new_attach_id ) ) {
			wp_send_json_error( array( 'message' => $new_attach_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'id'        => $new_attach_id,
			'url'       => wp_get_attachment_url( $new_attach_id ),
			'thumbnail' => wp_get_attachment_image_url( $new_attach_id, 'thumbnail' ),
			'message'   => sprintf(
				/* translators: %dx: scale factor */
				__( 'Image upscaled %dx successfully.', 'fotohub-ai' ),
				$scale
			),
		) );
	}

	/**
	 * Test API connection via AJAX.
	 */
	public static function fotohub_test_connection(): void {
		check_ajax_referer( 'fotohub_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fotohub-ai' ) ), 403 );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

		// If masked key, use stored key.
		if ( str_contains( $api_key, '***' ) || empty( $api_key ) ) {
			$api = new Fotohub_API();
		} else {
			$api = new Fotohub_API( $api_key );
		}

		if ( ! $api->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'No API key configured.', 'fotohub-ai' ) ) );
		}

		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$balance = $result['balance']['credits'] ?? $result['balance']['balance'] ?? 'N/A';

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %s: credit balance */
				__( 'Connected! Balance: %s credits', 'fotohub-ai' ),
				$balance
			),
		) );
	}

	/**
	 * Bulk generate images via AJAX.
	 */
	public static function fotohub_bulk_generate(): void {
		self::verify_request();

		$prompts = array_filter( array_map(
			'sanitize_textarea_field',
			(array) ( wp_unslash( $_POST['prompts'] ?? array() ) )
		) );

		if ( empty( $prompts ) ) {
			wp_send_json_error( array( 'message' => __( 'No prompts provided.', 'fotohub-ai' ) ) );
		}

		$model   = sanitize_text_field( $_POST['model'] ?? '' );
		$width   = absint( $_POST['width'] ?? 0 );
		$height  = absint( $_POST['height'] ?? 0 );
		$api     = new Fotohub_API();
		$results = array();
		$errors  = array();

		foreach ( $prompts as $index => $prompt ) {
			$options = array_filter( array(
				'model'  => $model,
				'width'  => $width,
				'height' => $height,
			) );

			$result = $api->generate_image( $prompt, $options );

			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'index'   => $index,
					'prompt'  => $prompt,
					'message' => $result->get_error_message(),
				);
				continue;
			}

			$images = $result['images'] ?? array( $result );
			foreach ( $images as $image ) {
				$image_url = $image['url'] ?? $image['image_url'] ?? '';
				if ( empty( $image_url ) ) {
					continue;
				}

				$attachment_id = Fotohub_Media::sideload_image( $image_url );
				if ( ! is_wp_error( $attachment_id ) ) {
					$results[] = array(
						'index'     => $index,
						'prompt'    => $prompt,
						'id'        => $attachment_id,
						'url'       => wp_get_attachment_url( $attachment_id ),
						'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
					);
				}
			}
		}

		wp_send_json_success( array(
			'results' => $results,
			'errors'  => $errors,
			'message' => sprintf(
				/* translators: %1$d: successes, %2$d: total */
				__( 'Generated %1$d of %2$d images.', 'fotohub-ai' ),
				count( $results ),
				count( $prompts )
			),
		) );
	}

	/**
	 * Generate product photos via AJAX.
	 */
	public static function fotohub_generate_product_photos(): void {
		self::verify_request();

		$product_id  = absint( $_POST['product_id'] ?? 0 );
		$custom_prompt = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
		$num_images  = min( absint( $_POST['num_images'] ?? 4 ), 4 );
		$style       = sanitize_text_field( $_POST['style'] ?? 'product' );

		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product or WooCommerce not active.', 'fotohub-ai' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'fotohub-ai' ) ) );
		}

		$prompt = ! empty( $custom_prompt ) ? $custom_prompt : Fotohub_Bulk::build_product_prompt( $product, $style );

		$api    = new Fotohub_API();
		$result = $api->generate_image( $prompt, array(
			'num_images' => $num_images,
			'width'      => 1024,
			'height'     => 1024,
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$attachments = array();
		$images      = $result['images'] ?? array( $result );

		foreach ( $images as $image ) {
			$image_url = $image['url'] ?? $image['image_url'] ?? '';
			if ( empty( $image_url ) ) {
				continue;
			}

			$attachment_id = Fotohub_Media::sideload_image( $image_url, $product_id );
			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			// Attach to product.
			if ( ! $product->get_image_id() ) {
				$product->set_image_id( $attachment_id );
			} else {
				$gallery_ids   = $product->get_gallery_image_ids();
				$gallery_ids[] = $attachment_id;
				$product->set_gallery_image_ids( $gallery_ids );
			}

			$attachments[] = array(
				'id'        => $attachment_id,
				'url'       => wp_get_attachment_url( $attachment_id ),
				'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			);
		}

		$product->save();

		wp_send_json_success( array(
			'images'  => $attachments,
			'message' => sprintf(
				/* translators: %d: number of images */
				_n( '%d product photo generated.', '%d product photos generated.', count( $attachments ), 'fotohub-ai' ),
				count( $attachments )
			),
		) );
	}

	/**
	 * Generate video via AJAX.
	 */
	public static function fotohub_generate_video(): void {
		self::verify_request();

		$prompt = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
		if ( empty( $prompt ) ) {
			wp_send_json_error( array( 'message' => __( 'Prompt is required.', 'fotohub-ai' ) ) );
		}

		$options = array(
			'model'        => sanitize_text_field( $_POST['model'] ?? 'veo-2' ),
			'duration'     => absint( $_POST['duration'] ?? 4 ),
			'aspect_ratio' => sanitize_text_field( $_POST['aspect_ratio'] ?? '16:9' ),
		);

		if ( ! empty( $_POST['image_url'] ) ) {
			$options['image_url'] = esc_url_raw( wp_unslash( $_POST['image_url'] ) );
		}
		if ( ! empty( $_POST['resolution'] ) ) {
			$options['resolution'] = sanitize_text_field( $_POST['resolution'] );
		}

		$api    = new Fotohub_API();
		$result = $api->generate_video( $prompt, $options );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Video generation is async - return job ID for polling
		$job_id  = $result['job_id'] ?? $result['id'] ?? '';
		$post_id = absint( $_POST['post_id'] ?? 0 );

		if ( $post_id && $job_id ) {
			$jobs   = get_post_meta( $post_id, '_fotohub_video_jobs', true ) ?: array();
			$jobs[] = array(
				'job_id'     => $job_id,
				'prompt'     => $prompt,
				'status'     => 'processing',
				'created_at' => current_time( 'mysql' ),
			);
			update_post_meta( $post_id, '_fotohub_video_jobs', $jobs );
		}

		wp_send_json_success( array(
			'job_id'  => $job_id,
			'status'  => $result['status'] ?? 'processing',
			'message' => __( 'Video generation started. This may take 1-5 minutes.', 'fotohub-ai' ),
		) );
	}

	/**
	 * Check video generation status via AJAX.
	 */
	public static function fotohub_check_video_status(): void {
		self::verify_request();

		$job_id = sanitize_text_field( $_POST['job_id'] ?? '' );
		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No job ID provided.', 'fotohub-ai' ) ) );
		}

		$api    = new Fotohub_API();
		$result = $api->get_balance(); // Placeholder - actual status check would be GET /v1/ai/jobs/{job_id}
		// For now we check via the generate endpoint response
		// The real implementation polls job status

		wp_send_json_success( array(
			'job_id'  => $job_id,
			'status'  => 'processing',
			'message' => __( 'Still processing...', 'fotohub-ai' ),
		) );
	}

	/**
	 * Run a Stability AI tool via AJAX.
	 */
	public static function fotohub_stability_tool(): void {
		self::verify_request();

		$tool_id       = sanitize_text_field( $_POST['tool_id'] ?? '' );
		$attachment_id = absint( $_POST['attachment_id'] ?? 0 );

		if ( empty( $tool_id ) || ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Tool ID and image are required.', 'fotohub-ai' ) ) );
		}

		// Get image as base64
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Image file not found.', 'fotohub-ai' ) ) );
		}

		$image_base64 = base64_encode( file_get_contents( $file_path ) );
		$options      = array(
			'output_format' => sanitize_text_field( $_POST['output_format'] ?? 'png' ),
		);

		// Handle tool-specific options
		if ( ! empty( $_POST['prompt'] ) ) {
			$options['prompt'] = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) );
		}
		if ( ! empty( $_POST['mask'] ) ) {
			$options['mask'] = sanitize_text_field( $_POST['mask'] ); // base64 from canvas
		}
		if ( ! empty( $_POST['reference_id'] ) ) {
			$ref_path = get_attached_file( absint( $_POST['reference_id'] ) );
			if ( $ref_path && file_exists( $ref_path ) ) {
				$options['reference'] = base64_encode( file_get_contents( $ref_path ) );
			}
		}
		if ( ! empty( $_POST['search_prompt'] ) ) {
			$options['search_prompt'] = sanitize_text_field( wp_unslash( $_POST['search_prompt'] ) );
		}
		if ( ! empty( $_POST['replace_prompt'] ) ) {
			$options['replace_prompt'] = sanitize_text_field( wp_unslash( $_POST['replace_prompt'] ) );
		}
		if ( ! empty( $_POST['color'] ) ) {
			$options['color'] = sanitize_text_field( $_POST['color'] );
		}
		if ( ! empty( $_POST['padding'] ) ) {
			$options['padding'] = array_map( 'absint', (array) $_POST['padding'] );
		}

		$api    = new Fotohub_API();
		$result = $api->stability_tool( $tool_id, $image_base64, $options );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Result contains base64 image - save to media library
		$output_base64 = $result['image'] ?? $result['output'] ?? '';
		if ( ! empty( $output_base64 ) ) {
			$upload_dir = wp_upload_dir();
			$filename   = 'fotohub-stability-' . $tool_id . '-' . time() . '.' . ( $options['output_format'] ?? 'png' );
			$filepath   = $upload_dir['path'] . '/' . $filename;

			file_put_contents( $filepath, base64_decode( $output_base64 ) );

			$attachment = array(
				'post_mime_type' => 'image/' . ( $options['output_format'] ?? 'png' ),
				'post_title'    => sanitize_file_name( $filename ),
				'post_content'  => '',
				'post_status'   => 'inherit',
			);

			$new_attach_id = wp_insert_attachment( $attachment, $filepath, wp_get_post_parent_id( $attachment_id ) );
			if ( ! is_wp_error( $new_attach_id ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$attach_data = wp_generate_attachment_metadata( $new_attach_id, $filepath );
				wp_update_attachment_metadata( $new_attach_id, $attach_data );
			}

			wp_send_json_success( array(
				'id'        => $new_attach_id,
				'url'       => wp_get_attachment_url( $new_attach_id ),
				'thumbnail' => wp_get_attachment_image_url( $new_attach_id, 'thumbnail' ),
				'message'   => sprintf( __( 'Stability %s completed successfully.', 'fotohub-ai' ), $tool_id ),
			) );
		}

		// Fallback: if result has a URL instead
		$result_url = $result['url'] ?? $result['image_url'] ?? '';
		if ( ! empty( $result_url ) ) {
			$new_attach_id = Fotohub_Media::sideload_image( $result_url, wp_get_post_parent_id( $attachment_id ) );
			if ( is_wp_error( $new_attach_id ) ) {
				wp_send_json_error( array( 'message' => $new_attach_id->get_error_message() ) );
			}
			wp_send_json_success( array(
				'id'        => $new_attach_id,
				'url'       => wp_get_attachment_url( $new_attach_id ),
				'thumbnail' => wp_get_attachment_image_url( $new_attach_id, 'thumbnail' ),
				'message'   => sprintf( __( 'Stability %s completed successfully.', 'fotohub-ai' ), $tool_id ),
			) );
		}

		wp_send_json_error( array( 'message' => __( 'No output received from API.', 'fotohub-ai' ) ) );
	}

	/**
	 * Generate AI copy via AJAX.
	 */
	public static function fotohub_generate_copy(): void {
		self::verify_request();

		$type    = sanitize_text_field( $_POST['type'] ?? '' );
		$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
		$tone    = sanitize_text_field( $_POST['tone'] ?? 'professional' );

		if ( empty( $type ) ) {
			wp_send_json_error( array( 'message' => __( 'Copy type is required.', 'fotohub-ai' ) ) );
		}

		$api            = new Fotohub_API();
		$system_prompts = array(
			'title'               => 'You are an expert headline writer. Generate 5 compelling title options. Return them as a JSON array of strings.',
			'excerpt'             => 'You are an SEO expert. Generate a compelling meta description (max 160 chars) for the following content.',
			'article'             => 'You are a professional content writer. Write a well-structured article based on the following outline. Use markdown formatting.',
			'product_description' => 'You are a product copywriter. Write a compelling product description in a ' . $tone . ' tone.',
			'slug'                => 'You are an SEO expert. Generate an SEO-optimized URL slug for the following title. Return only the slug, lowercase, hyphenated.',
			'alt_text'            => 'You are an accessibility expert. Generate descriptive alt text for images.',
		);

		$system   = $system_prompts[ $type ] ?? 'You are a professional copywriter.';
		$messages = array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $content ),
		);

		$result = $api->chat( $messages, array(
			'model'       => 'claude-sonnet-4-20250514',
			'temperature' => ( $type === 'title' ) ? 0.8 : 0.6,
			'max_tokens'  => ( $type === 'article' ) ? 4000 : 1000,
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$output = $result['choices'][0]['message']['content'] ?? $result['content'] ?? '';

		wp_send_json_success( array(
			'content' => $output,
			'type'    => $type,
			'message' => sprintf( __( '%s generated successfully.', 'fotohub-ai' ), ucfirst( str_replace( '_', ' ', $type ) ) ),
		) );
	}

	/**
	 * Analyze image via AJAX (for alt text generation).
	 */
	public static function fotohub_analyze_image(): void {
		self::verify_request();

		$attachment_id = absint( $_POST['attachment_id'] ?? 0 );
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'No image selected.', 'fotohub-ai' ) ) );
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			wp_send_json_error( array( 'message' => __( 'Could not get image URL.', 'fotohub-ai' ) ) );
		}

		$api    = new Fotohub_API();
		$result = $api->analyze_image( $image_url, array( 'description', 'alt_text', 'tags' ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$alt_text = $result['alt_text'] ?? $result['description'] ?? '';
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}

		wp_send_json_success( array(
			'alt_text'    => $alt_text,
			'description' => $result['description'] ?? '',
			'tags'        => $result['tags'] ?? array(),
			'message'     => __( 'Image analyzed successfully.', 'fotohub-ai' ),
		) );
	}

	/**
	 * Bulk generate alt text via AJAX.
	 */
	public static function fotohub_bulk_alt_text(): void {
		self::verify_request();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fotohub-ai' ) ), 403 );
		}

		$batch_size = min( absint( $_POST['batch_size'] ?? 10 ), 50 );

		// Find images without alt text
		global $wpdb;
		$attachments = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
			 WHERE p.post_type = 'attachment'
			 AND p.post_mime_type LIKE %s
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')
			 ORDER BY p.ID DESC
			 LIMIT %d",
			'image/%',
			$batch_size
		) );

		if ( empty( $attachments ) ) {
			wp_send_json_success( array(
				'processed' => 0,
				'message'   => __( 'All images already have alt text!', 'fotohub-ai' ),
			) );
		}

		$api       = new Fotohub_API();
		$processed = 0;
		$errors    = 0;

		foreach ( $attachments as $attachment_id ) {
			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				$errors++;
				continue;
			}

			$result = $api->analyze_image( $image_url, array( 'alt_text' ) );
			if ( is_wp_error( $result ) ) {
				$errors++;
				continue;
			}

			$alt_text = $result['alt_text'] ?? $result['description'] ?? '';
			if ( ! empty( $alt_text ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
				$processed++;
			}
		}

		wp_send_json_success( array(
			'processed' => $processed,
			'errors'    => $errors,
			'remaining' => max( 0, count( $attachments ) - $processed ),
			'message'   => sprintf(
				__( 'Generated alt text for %d images. %d errors.', 'fotohub-ai' ),
				$processed,
				$errors
			),
		) );
	}

	/**
	 * Schedule a generation job via AJAX.
	 */
	public static function fotohub_schedule_job(): void {
		self::verify_request();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fotohub-ai' ) ), 403 );
		}

		$job_type     = sanitize_text_field( $_POST['job_type'] ?? '' );
		$payload      = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : array();
		$scheduled_at = sanitize_text_field( $_POST['scheduled_at'] ?? '' );

		if ( empty( $job_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Job type is required.', 'fotohub-ai' ) ) );
		}

		if ( class_exists( 'Fotohub_Scheduler' ) ) {
			$job_id = Fotohub_Scheduler::schedule_job( $job_type, $payload, $scheduled_at ?: null );
			wp_send_json_success( array(
				'job_id'  => $job_id,
				'message' => __( 'Job scheduled successfully.', 'fotohub-ai' ),
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Scheduler not available.', 'fotohub-ai' ) ) );
	}

	/**
	 * Get analytics data via AJAX.
	 */
	public static function fotohub_get_analytics(): void {
		self::verify_request();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fotohub-ai' ) ), 403 );
		}

		$period = sanitize_text_field( $_POST['period'] ?? 'month' );

		if ( class_exists( 'Fotohub_Analytics' ) ) {
			$stats  = Fotohub_Analytics::get_monthly_stats( $period );
			$models = Fotohub_Analytics::get_model_breakdown( $period );

			$api     = new Fotohub_API();
			$balance = $api->get_cached_balance();

			wp_send_json_success( array(
				'stats'   => $stats,
				'models'  => $models,
				'balance' => is_wp_error( $balance ) ? null : $balance,
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Analytics not available.', 'fotohub-ai' ) ) );
	}

	/**
	 * Estimate cost via AJAX.
	 */
	public static function fotohub_estimate_cost(): void {
		self::verify_request();

		$operations = isset( $_POST['operations'] ) ? json_decode( wp_unslash( $_POST['operations'] ), true ) : array();
		if ( empty( $operations ) ) {
			wp_send_json_error( array( 'message' => __( 'No operations provided.', 'fotohub-ai' ) ) );
		}

		$api    = new Fotohub_API();
		$result = $api->estimate_cost( $operations );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'estimate' => $result,
			'message'  => __( 'Cost estimated.', 'fotohub-ai' ),
		) );
	}

	/**
	 * Export analytics as CSV via AJAX.
	 */
	public static function fotohub_export_csv(): void {
		self::verify_request();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fotohub-ai' ) ), 403 );
		}

		if ( class_exists( 'Fotohub_Analytics' ) ) {
			$csv_url = Fotohub_Analytics::export_csv();
			wp_send_json_success( array(
				'url'     => $csv_url,
				'message' => __( 'Export ready for download.', 'fotohub-ai' ),
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Analytics not available.', 'fotohub-ai' ) ) );
	}
}
