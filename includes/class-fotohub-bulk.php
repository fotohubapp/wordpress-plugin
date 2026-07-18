<?php
/**
 * FOTOhub Bulk Generation & WooCommerce Integration.
 *
 * Provides bulk image generation for WooCommerce products.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk generation and WooCommerce class.
 */
class Fotohub_Bulk {

	/**
	 * Initialize bulk/WooCommerce hooks.
	 */
	public static function init(): void {
		// WooCommerce product edit page.
		add_action( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_data_panel' ) );

		// WooCommerce product bulk actions.
		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'add_product_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'handle_product_bulk_actions' ), 10, 3 );

		// Admin notices.
		add_action( 'admin_notices', array( __CLASS__, 'product_bulk_notices' ) );
	}

	/**
	 * Add FOTOhub tab to WooCommerce product data.
	 *
	 * @param array $tabs Existing product data tabs.
	 */
	public static function add_product_data_tab( array $tabs ): array {
		$tabs['fotohub_ai'] = array(
			'label'    => __( 'FOTOhub AI', 'fotohub-ai' ),
			'target'   => 'fotohub_ai_product_data',
			'class'    => array(),
			'priority' => 80,
		);
		return $tabs;
	}

	/**
	 * Render product data panel.
	 */
	public static function render_product_data_panel(): void {
		global $post;
		?>
		<div id="fotohub_ai_product_data" class="panel woocommerce_options_panel">
			<div class="options_group fotohub-product-panel">
				<p class="form-field">
					<label><?php esc_html_e( 'Generate Product Photos', 'fotohub-ai' ); ?></label>
					<span class="description">
						<?php esc_html_e( 'Use AI to generate product images based on the product title and description.', 'fotohub-ai' ); ?>
					</span>
				</p>

				<p class="form-field">
					<label for="fotohub_product_prompt"><?php esc_html_e( 'Custom Prompt (optional)', 'fotohub-ai' ); ?></label>
					<textarea id="fotohub_product_prompt" name="fotohub_product_prompt" rows="3"
							  class="large-text" placeholder="<?php esc_attr_e( 'Leave empty to auto-generate from product title and description', 'fotohub-ai' ); ?>"></textarea>
				</p>

				<p class="form-field">
					<label for="fotohub_product_num_images"><?php esc_html_e( 'Number of Images', 'fotohub-ai' ); ?></label>
					<select id="fotohub_product_num_images" name="fotohub_product_num_images">
						<option value="1">1</option>
						<option value="2">2</option>
						<option value="3">3</option>
						<option value="4" selected>4</option>
					</select>
				</p>

				<p class="form-field">
					<label for="fotohub_product_style"><?php esc_html_e( 'Photo Style', 'fotohub-ai' ); ?></label>
					<select id="fotohub_product_style" name="fotohub_product_style">
						<option value="product"><?php esc_html_e( 'Product Photography (White BG)', 'fotohub-ai' ); ?></option>
						<option value="lifestyle"><?php esc_html_e( 'Lifestyle Shot', 'fotohub-ai' ); ?></option>
						<option value="studio"><?php esc_html_e( 'Studio Lighting', 'fotohub-ai' ); ?></option>
						<option value="flat-lay"><?php esc_html_e( 'Flat Lay', 'fotohub-ai' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom (use prompt above)', 'fotohub-ai' ); ?></option>
					</select>
				</p>

				<p class="form-field">
					<button type="button" class="button button-primary fotohub-generate-product-photos"
							data-product-id="<?php echo esc_attr( $post->ID ); ?>">
						<span class="dashicons dashicons-camera" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Generate Product Photos', 'fotohub-ai' ); ?>
					</button>
					<span class="fotohub-product-status"></span>
				</p>

				<div class="fotohub-product-results" style="display:none;">
					<p><strong><?php esc_html_e( 'Generated Images:', 'fotohub-ai' ); ?></strong></p>
					<div class="fotohub-product-gallery"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add bulk actions to products list.
	 *
	 * @param array $actions Existing bulk actions.
	 */
	public static function add_product_bulk_actions( array $actions ): array {
		if ( Fotohub_API::get_api_key() ) {
			$actions['fotohub_generate_photos'] = __( 'Generate AI Photos (FOTOhub)', 'fotohub-ai' );
		}
		return $actions;
	}

	/**
	 * Handle product bulk actions.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       The action.
	 * @param array  $post_ids     Selected post IDs.
	 */
	public static function handle_product_bulk_actions( string $redirect_url, string $action, array $post_ids ): string {
		if ( 'fotohub_generate_photos' !== $action ) {
			return $redirect_url;
		}

		$api       = new Fotohub_API();
		$processed = 0;
		$errors    = 0;

		foreach ( $post_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$prompt = self::build_product_prompt( $product );
			$result = $api->generate_image( $prompt, array(
				'num_images' => 2,
				'width'      => 1024,
				'height'     => 1024,
			) );

			if ( is_wp_error( $result ) ) {
				$errors++;
				continue;
			}

			// Attach generated images to the product.
			$images = $result['images'] ?? array( $result );
			foreach ( $images as $image ) {
				$image_url = $image['url'] ?? $image['image_url'] ?? '';
				if ( empty( $image_url ) ) {
					continue;
				}

				$attachment_id = Fotohub_Media::sideload_image( $image_url, $product_id );
				if ( ! is_wp_error( $attachment_id ) ) {
					self::attach_image_to_product( $product, $attachment_id );
				}
			}

			$processed++;
		}

		return add_query_arg( array(
			'fotohub_products_processed' => $processed,
			'fotohub_products_errors'    => $errors,
		), $redirect_url );
	}

	/**
	 * Display product bulk action notices.
	 */
	public static function product_bulk_notices(): void {
		if ( ! empty( $_GET['fotohub_products_processed'] ) ) {
			$count = absint( $_GET['fotohub_products_processed'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: number of products */
					esc_html( _n(
						'AI photos generated for %d product.',
						'AI photos generated for %d products.',
						$count,
						'fotohub-ai'
					) ),
					$count
				)
			);
		}
	}

	/**
	 * Build a generation prompt from a WooCommerce product.
	 *
	 * @param WC_Product $product The product object.
	 * @param string     $style   Photo style preset.
	 */
	public static function build_product_prompt( $product, string $style = 'product' ): string {
		$name        = $product->get_name();
		$description = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
		$description = wp_trim_words( $description, 30, '' );

		$style_prefix = match ( $style ) {
			'lifestyle' => 'Lifestyle product photography of',
			'studio'    => 'Professional studio photography with dramatic lighting of',
			'flat-lay'  => 'Flat lay product photography from above of',
			default     => 'Professional product photography on clean white background of',
		};

		$prompt = $style_prefix . ' ' . $name;
		if ( ! empty( $description ) ) {
			$prompt .= '. ' . $description;
		}
		$prompt .= '. High resolution, commercial quality, e-commerce ready.';

		return $prompt;
	}

	/**
	 * Attach an image to a WooCommerce product gallery.
	 *
	 * @param WC_Product $product       The product.
	 * @param int        $attachment_id The attachment ID.
	 */
	private static function attach_image_to_product( $product, int $attachment_id ): void {
		// If product has no featured image, set this one.
		if ( ! $product->get_image_id() ) {
			$product->set_image_id( $attachment_id );
		} else {
			// Add to gallery.
			$gallery_ids   = $product->get_gallery_image_ids();
			$gallery_ids[] = $attachment_id;
			$product->set_gallery_image_ids( $gallery_ids );
		}
		$product->save();
	}
}
