<?php
/**
 * FOTOhub AI Copywriter.
 *
 * Provides AI-powered content generation: titles, excerpts, articles,
 * product descriptions, alt text, and SEO slugs via FOTOhub chat API.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI copywriting class.
 */
class Fotohub_Copywriter {

	/**
	 * Default model for text generation tasks.
	 */
	private const MODEL = 'claude-sonnet-4-20250514';

	/**
	 * REST API namespace.
	 */
	private const REST_NAMESPACE = 'fotohub-ai/v1';

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_gutenberg_assets' ) );
	}

	/**
	 * Register the AI Writer meta box for classic editor.
	 */
	public static function register_meta_box(): void {
		$post_types = array( 'post', 'page', 'product' );

		foreach ( $post_types as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				add_meta_box(
					'fotohub-ai-writer',
					__( 'FOTOhub AI Writer', 'fotohub-ai' ),
					array( __CLASS__, 'render_meta_box' ),
					$post_type,
					'side',
					'high'
				);
			}
		}
	}

	/**
	 * Render the AI Writer meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_meta_box( WP_Post $post ): void {
		if ( ! Fotohub_API::get_api_key() ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'Please configure your API key in', 'fotohub-ai' ),
				esc_url( admin_url( 'options-general.php?page=fotohub-ai-settings' ) ),
				esc_html__( 'FOTOhub Settings', 'fotohub-ai' )
			);
			return;
		}

		wp_nonce_field( 'fotohub_ai_writer', 'fotohub_ai_writer_nonce' );
		?>
		<div class="fotohub-ai-writer-panel">
			<p class="description">
				<?php esc_html_e( 'Use AI to generate content for this post.', 'fotohub-ai' ); ?>
			</p>

			<div class="fotohub-ai-writer-actions">
				<button type="button" class="button fotohub-ai-btn" data-action="generate_titles">
					<span class="dashicons dashicons-edit" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Suggest Titles', 'fotohub-ai' ); ?>
				</button>

				<button type="button" class="button fotohub-ai-btn" data-action="generate_excerpt">
					<span class="dashicons dashicons-editor-paragraph" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Generate Excerpt', 'fotohub-ai' ); ?>
				</button>

				<button type="button" class="button fotohub-ai-btn" data-action="generate_slug">
					<span class="dashicons dashicons-admin-links" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'SEO Slug', 'fotohub-ai' ); ?>
				</button>

				<button type="button" class="button fotohub-ai-btn" data-action="generate_article">
					<span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Article from Outline', 'fotohub-ai' ); ?>
				</button>

				<?php if ( 'product' === $post->post_type ) : ?>
					<button type="button" class="button fotohub-ai-btn" data-action="generate_product_description">
						<span class="dashicons dashicons-cart" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'Product Description', 'fotohub-ai' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="fotohub-ai-writer-options" style="margin-top: 10px;">
				<label for="fotohub-ai-tone">
					<?php esc_html_e( 'Tone:', 'fotohub-ai' ); ?>
				</label>
				<select id="fotohub-ai-tone" name="fotohub_ai_tone">
					<option value="professional"><?php esc_html_e( 'Professional', 'fotohub-ai' ); ?></option>
					<option value="casual"><?php esc_html_e( 'Casual', 'fotohub-ai' ); ?></option>
					<option value="luxury"><?php esc_html_e( 'Luxury', 'fotohub-ai' ); ?></option>
					<option value="technical"><?php esc_html_e( 'Technical', 'fotohub-ai' ); ?></option>
				</select>
			</div>

			<div id="fotohub-ai-writer-output" style="margin-top: 10px; display: none;">
				<h4><?php esc_html_e( 'AI Suggestions:', 'fotohub-ai' ); ?></h4>
				<div id="fotohub-ai-writer-results"></div>
			</div>

			<div id="fotohub-ai-writer-spinner" style="display: none;">
				<span class="spinner is-active" style="float: none;"></span>
				<?php esc_html_e( 'Generating...', 'fotohub-ai' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue Gutenberg sidebar panel assets.
	 */
	public static function enqueue_gutenberg_assets(): void {
		if ( ! Fotohub_API::get_api_key() ) {
			return;
		}

		wp_enqueue_script(
			'fotohub-ai-writer-gutenberg',
			FOTOHUB_AI_PLUGIN_URL . 'admin/js/gutenberg-ai-writer.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
			FOTOHUB_AI_VERSION,
			true
		);

		wp_localize_script( 'fotohub-ai-writer-gutenberg', 'fotohubAIWriter', array(
			'restUrl' => rest_url( self::REST_NAMESPACE ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'panelTitle'  => __( 'FOTOhub AI Writer', 'fotohub-ai' ),
				'generating'  => __( 'Generating...', 'fotohub-ai' ),
				'titles'      => __( 'Suggest Titles', 'fotohub-ai' ),
				'excerpt'     => __( 'Generate Excerpt', 'fotohub-ai' ),
				'article'     => __( 'Article from Outline', 'fotohub-ai' ),
				'slug'        => __( 'SEO Slug', 'fotohub-ai' ),
				'product'     => __( 'Product Description', 'fotohub-ai' ),
				'altText'     => __( 'Generate Alt Text', 'fotohub-ai' ),
				'bulkAltText' => __( 'Bulk Alt Text', 'fotohub-ai' ),
			),
		) );
	}

	/**
	 * Register REST API routes for Gutenberg integration.
	 */
	public static function register_rest_routes(): void {
		register_rest_route( self::REST_NAMESPACE, '/generate-titles', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_generate_titles' ),
			'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
			'args'                => array(
				'topic' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'count' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 5,
				),
			),
		) );

		register_rest_route( self::REST_NAMESPACE, '/generate-excerpt', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_generate_excerpt' ),
			'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
			'args'                => array(
				'content' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
			),
		) );

		register_rest_route( self::REST_NAMESPACE, '/generate-article', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_generate_article' ),
			'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
			'args'                => array(
				'outline' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
			),
		) );

		register_rest_route( self::REST_NAMESPACE, '/generate-product-description', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_generate_product_description' ),
			'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
			'args'                => array(
				'product_name' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'details'      => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
				'tone'         => array(
					'required' => false,
					'type'     => 'string',
					'default'  => 'professional',
					'enum'     => array( 'professional', 'casual', 'luxury', 'technical' ),
				),
			),
		) );

		register_rest_route( self::REST_NAMESPACE, '/generate-alt-text', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_generate_alt_text' ),
			'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
			'args'                => array(
				'attachment_id' => array(
					'required' => true,
					'type'     => 'integer',
				),
			),
		) );

		register_rest_route( self::REST_NAMESPACE, '/generate-slug', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_generate_slug' ),
			'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
			'args'                => array(
				'title' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( self::REST_NAMESPACE, '/bulk-alt-text', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_bulk_generate_alt_text' ),
			'permission_callback' => array( __CLASS__, 'rest_permission_check' ),
			'args'                => array(
				'batch_size' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 20,
				),
			),
		) );
	}

	/**
	 * REST API permission check.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public static function rest_permission_check( WP_REST_Request $request ): bool {
		return current_user_can( 'edit_posts' ) && ! empty( Fotohub_API::get_api_key() );
	}

	/**
	 * REST callback: Generate title suggestions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_generate_titles( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$topic = $request->get_param( 'topic' );
		$count = $request->get_param( 'count' );

		$result = self::generate_titles( $topic, $count );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'titles' => $result ), 200 );
	}

	/**
	 * REST callback: Generate excerpt.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_generate_excerpt( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$content = $request->get_param( 'content' );

		$result = self::generate_excerpt( $content );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'excerpt' => $result ), 200 );
	}

	/**
	 * REST callback: Generate article from outline.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_generate_article( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$outline = $request->get_param( 'outline' );

		$result = self::generate_article( $outline );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'article' => $result ), 200 );
	}

	/**
	 * REST callback: Generate product description.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_generate_product_description( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$product_name = $request->get_param( 'product_name' );
		$details      = $request->get_param( 'details' );
		$tone         = $request->get_param( 'tone' );

		$result = self::generate_product_description( $product_name, $details, $tone );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'description' => $result ), 200 );
	}

	/**
	 * REST callback: Generate alt text for an attachment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_generate_alt_text( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = $request->get_param( 'attachment_id' );

		$result = self::generate_alt_text( $attachment_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'alt_text' => $result ), 200 );
	}

	/**
	 * REST callback: Generate SEO slug.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_generate_slug( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$title = $request->get_param( 'title' );

		$result = self::generate_slug( $title );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'slug' => $result ), 200 );
	}

	/**
	 * REST callback: Bulk generate alt text.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_bulk_generate_alt_text( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$batch_size = $request->get_param( 'batch_size' );

		$result = self::bulk_generate_alt_text( $batch_size );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Generate post title suggestions.
	 *
	 * @param string $topic The topic or theme for title generation.
	 * @param int    $count Number of title suggestions to generate.
	 * @return array|WP_Error Array of title strings or WP_Error.
	 */
	public static function generate_titles( string $topic, int $count = 5 ): array|WP_Error {
		$count = min( max( $count, 1 ), 10 );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an expert SEO copywriter and headline specialist. Generate compelling, click-worthy post titles that are optimized for search engines while remaining engaging for readers. Return only the titles as a JSON array of strings, with no additional text or explanation.',
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					'Generate %d unique blog post title suggestions for the following topic: "%s". Make them varied in style (question, how-to, listicle, statement). Return as a JSON array.',
					$count,
					$topic
				),
			),
		);

		$response = self::chat_completion( $messages, 0.8, 500 );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$content = $response['choices'][0]['message']['content'] ?? '';
		$titles  = json_decode( $content, true );

		if ( ! is_array( $titles ) ) {
			// Attempt to extract titles from plain text response.
			$lines  = array_filter( explode( "\n", $content ) );
			$titles = array_map( function ( string $line ): string {
				return trim( preg_replace( '/^\d+[\.\)]\s*/', '', $line ) );
			}, $lines );
			$titles = array_filter( $titles );
			$titles = array_values( $titles );
		}

		return array_slice( $titles, 0, $count );
	}

	/**
	 * Generate a post excerpt / meta description from content.
	 *
	 * @param string $content The post content to summarize.
	 * @return string|WP_Error Generated excerpt or WP_Error.
	 */
	public static function generate_excerpt( string $content ): string|WP_Error {
		$content = wp_strip_all_tags( $content );
		// Limit input to avoid excessive token usage.
		$content = mb_substr( $content, 0, 5000 );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an SEO expert specializing in meta descriptions and post excerpts. Generate a concise, compelling excerpt that summarizes the content in 150-160 characters. The excerpt should be informative, include relevant keywords naturally, and entice readers to click. Return only the excerpt text, no quotes or extra formatting.',
			),
			array(
				'role'    => 'user',
				'content' => sprintf( 'Generate an SEO-optimized excerpt/meta description for this content:\n\n%s', $content ),
			),
		);

		$response = self::chat_completion( $messages, 0.5, 200 );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$excerpt = $response['choices'][0]['message']['content'] ?? '';
		return trim( $excerpt );
	}

	/**
	 * Generate a full article from an outline.
	 *
	 * @param string $outline The article outline or structure.
	 * @return string|WP_Error Generated article in HTML or WP_Error.
	 */
	public static function generate_article( string $outline ): string|WP_Error {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a professional content writer and SEO specialist. Write a comprehensive, well-structured article based on the provided outline. Use proper HTML formatting with h2, h3 headings, paragraphs, and lists where appropriate. The article should be engaging, informative, and optimized for search engines. Include transition sentences between sections. Write in a clear, authoritative tone. Return only the article HTML content.',
			),
			array(
				'role'    => 'user',
				'content' => sprintf( "Write a full article based on this outline:\n\n%s", $outline ),
			),
		);

		$response = self::chat_completion( $messages, 0.7, 4000 );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$article = $response['choices'][0]['message']['content'] ?? '';
		return wp_kses_post( trim( $article ) );
	}

	/**
	 * Generate a product description.
	 *
	 * @param string $product_name Name of the product.
	 * @param string $details      Product details, features, specifications.
	 * @param string $tone         Writing tone (professional, casual, luxury, technical).
	 * @return string|WP_Error Generated description or WP_Error.
	 */
	public static function generate_product_description( string $product_name, string $details, string $tone = 'professional' ): string|WP_Error {
		$tone_instructions = match ( $tone ) {
			'casual'      => 'Write in a friendly, conversational tone. Use simple language, contractions, and a warm approachable style. Make the reader feel like a friend is recommending the product.',
			'luxury'      => 'Write in an elegant, sophisticated tone. Use evocative language, sensory details, and emphasize exclusivity, craftsmanship, and premium quality. Appeal to aspirational emotions.',
			'technical'   => 'Write in a precise, specification-focused tone. Emphasize technical details, performance metrics, compatibility, and engineering quality. Use industry terminology appropriately.',
			default       => 'Write in a clear, professional tone. Balance technical details with benefit-driven language. Be authoritative and trustworthy while remaining accessible.',
		};

		$messages = array(
			array(
				'role'    => 'system',
				'content' => sprintf(
					'You are an expert e-commerce copywriter specializing in product descriptions that convert. %s Generate a compelling product description with HTML formatting (use paragraphs, bullet lists for features, and emphasis where appropriate). Focus on benefits, not just features. Include a brief opening hook and close with a subtle call-to-action. Return only the HTML description.',
					$tone_instructions
				),
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"Write a product description for:\n\nProduct: %s\n\nDetails:\n%s",
					$product_name,
					$details
				),
			),
		);

		$response = self::chat_completion( $messages, 0.7, 1500 );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$description = $response['choices'][0]['message']['content'] ?? '';
		return wp_kses_post( trim( $description ) );
	}

	/**
	 * Generate alt text for an image attachment using image analysis.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return string|WP_Error Generated alt text or WP_Error.
	 */
	public static function generate_alt_text( int $attachment_id ): string|WP_Error {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'fotohub_not_image',
				__( 'The specified attachment is not an image.', 'fotohub-ai' )
			);
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			return new WP_Error(
				'fotohub_no_url',
				__( 'Could not retrieve image URL.', 'fotohub-ai' )
			);
		}

		$api = new Fotohub_API();

		$response = $api->analyze_image( $image_url, array( 'description', 'objects', 'context' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$description = $response['description'] ?? $response['analysis'] ?? '';

		if ( empty( $description ) ) {
			return new WP_Error(
				'fotohub_no_description',
				__( 'Image analysis did not return a description.', 'fotohub-ai' )
			);
		}

		// Enhance the raw description into proper alt text via chat.
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an accessibility and SEO expert. Convert the provided image description into concise, descriptive alt text. The alt text should be: under 125 characters, descriptive of the image content, useful for screen readers, and include relevant keywords naturally. Return only the alt text, no quotes.',
			),
			array(
				'role'    => 'user',
				'content' => sprintf( 'Generate alt text from this image description: %s', $description ),
			),
		);

		$chat_response = self::chat_completion( $messages, 0.3, 100 );
		if ( is_wp_error( $chat_response ) ) {
			// Fall back to raw description if chat fails.
			$alt_text = mb_substr( $description, 0, 125 );
		} else {
			$alt_text = $chat_response['choices'][0]['message']['content'] ?? $description;
		}

		$alt_text = sanitize_text_field( trim( $alt_text ) );

		// Save the alt text to the attachment.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		return $alt_text;
	}

	/**
	 * Generate an SEO-optimized slug from a title.
	 *
	 * @param string $title The post title to create a slug for.
	 * @return string|WP_Error Generated slug or WP_Error.
	 */
	public static function generate_slug( string $title ): string|WP_Error {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an SEO expert specializing in URL optimization. Generate a short, keyword-rich URL slug for the given title. The slug should: be lowercase, use hyphens as separators, remove stop words (the, a, an, is, in, at, of, etc.), be 3-6 words maximum, contain the primary keyword, and be human-readable. Return only the slug, no explanation.',
			),
			array(
				'role'    => 'user',
				'content' => sprintf( 'Generate an SEO-optimized URL slug for this title: "%s"', $title ),
			),
		);

		$response = self::chat_completion( $messages, 0.3, 50 );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$slug = $response['choices'][0]['message']['content'] ?? '';
		$slug = sanitize_title( trim( $slug ) );

		return $slug;
	}

	/**
	 * Bulk generate alt text for images without alt text.
	 *
	 * @param int $batch_size Number of images to process per batch.
	 * @return array|WP_Error Results array with processed/failed counts or WP_Error.
	 */
	public static function bulk_generate_alt_text( int $batch_size = 20 ): array|WP_Error {
		$batch_size = min( max( $batch_size, 1 ), 50 );

		// Query images without alt text.
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $batch_size,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$attachments = get_posts( $args );

		if ( empty( $attachments ) ) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'remaining' => 0,
				'message'   => __( 'All images already have alt text.', 'fotohub-ai' ),
			);
		}

		$processed = 0;
		$failed    = 0;
		$results   = array();

		foreach ( $attachments as $attachment ) {
			$result = self::generate_alt_text( $attachment->ID );

			if ( is_wp_error( $result ) ) {
				$failed++;
				$results[] = array(
					'id'    => $attachment->ID,
					'title' => $attachment->post_title,
					'error' => $result->get_error_message(),
				);
			} else {
				$processed++;
				$results[] = array(
					'id'       => $attachment->ID,
					'title'    => $attachment->post_title,
					'alt_text' => $result,
				);
			}
		}

		// Count remaining images without alt text.
		$remaining_query = new WP_Query( array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				),
			),
		) );

		return array(
			'processed' => $processed,
			'failed'    => $failed,
			'remaining' => max( 0, $remaining_query->found_posts - $processed ),
			'results'   => $results,
		);
	}

	/**
	 * Make a chat completion request to the FOTOhub API.
	 *
	 * @param array $messages    Array of message objects with role and content.
	 * @param float $temperature Sampling temperature (0.0-1.0).
	 * @param int   $max_tokens  Maximum tokens in the response.
	 * @return array|WP_Error API response or WP_Error.
	 */
	private static function chat_completion( array $messages, float $temperature = 0.7, int $max_tokens = 2000 ): array|WP_Error {
		$api = new Fotohub_API();

		return $api->chat( $messages, array(
			'model'       => self::MODEL,
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
		) );
	}
}
