<?php
/**
 * FOTOhub API Client.
 *
 * Handles all communication with the FOTOhub API using WordPress HTTP API.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API client class.
 */
class Fotohub_API {

	/**
	 * API base URL.
	 */
	private string $base_url;

	/**
	 * API key.
	 */
	private string $api_key;

	/**
	 * Request timeout in seconds.
	 */
	private int $timeout = 120;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Optional API key override.
	 */
	public function __construct( ?string $api_key = null ) {
		$this->base_url = FOTOHUB_AI_API_BASE;
		$this->api_key  = $api_key ?? self::get_api_key();
	}

	/**
	 * Get the stored API key (decrypted).
	 */
	public static function get_api_key(): string {
		$encrypted = get_option( 'fotohub_ai_api_key', '' );
		if ( empty( $encrypted ) ) {
			return '';
		}
		return self::decrypt( $encrypted );
	}

	/**
	 * Store the API key (encrypted).
	 *
	 * @param string $api_key The plain API key.
	 */
	public static function set_api_key( string $api_key ): bool {
		$encrypted = self::encrypt( $api_key );
		return update_option( 'fotohub_ai_api_key', $encrypted );
	}

	/**
	 * Encrypt a value using WordPress salts.
	 *
	 * @param string $value Plain text value.
	 */
	private static function encrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$key = self::get_encryption_key();
		$iv  = openssl_random_pseudo_bytes( 16 );

		$encrypted = openssl_encrypt( $value, 'aes-256-cbc', $key, 0, $iv );
		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a value using WordPress salts.
	 *
	 * @param string $value Encrypted value.
	 */
	private static function decrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$key  = self::get_encryption_key();
		$data = base64_decode( $value );

		if ( false === $data || strlen( $data ) < 17 ) {
			// Fallback: value might be stored unencrypted (migration from older version).
			return $value;
		}

		$iv        = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );

		$decrypted = openssl_decrypt( $encrypted, 'aes-256-cbc', $key, 0, $iv );
		if ( false === $decrypted ) {
			// Fallback: value might be stored unencrypted.
			return $value;
		}

		return $decrypted;
	}

	/**
	 * Get encryption key derived from WordPress salts.
	 */
	private static function get_encryption_key(): string {
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'fotohub-ai-default-key';
		return hash( 'sha256', $salt . 'fotohub_ai_encryption', true );
	}

	/**
	 * Check if the API is configured.
	 */
	public function is_configured(): bool {
		return ! empty( $this->api_key );
	}

	/**
	 * Generate an image.
	 *
	 * @param string $prompt  The image generation prompt.
	 * @param array  $options Generation options (model, width, height, num_images).
	 * @return array|WP_Error Response array with image URLs or WP_Error.
	 */
	public function generate_image( string $prompt, array $options = [] ): array|WP_Error {
		$body = array(
			'prompt'     => sanitize_text_field( $prompt ),
			'model'      => $options['model'] ?? get_option( 'fotohub_ai_default_model', 'seedream-5-0-260128' ),
			'width'      => absint( $options['width'] ?? get_option( 'fotohub_ai_default_width', 1024 ) ),
			'height'     => absint( $options['height'] ?? get_option( 'fotohub_ai_default_height', 1024 ) ),
			'num_images' => min( absint( $options['num_images'] ?? 1 ), 4 ),
		);

		if ( ! empty( $options['negative_prompt'] ) ) {
			$body['negative_prompt'] = sanitize_text_field( $options['negative_prompt'] );
		}

		return $this->post( '/v1/ai/generate/image', $body );
	}

	/**
	 * Remove background from an image.
	 *
	 * @param string $image_url The image URL to process.
	 * @return array|WP_Error Response array with processed image URL or WP_Error.
	 */
	public function remove_background( string $image_url ): array|WP_Error {
		return $this->post( '/v1/ai/remove-background', array(
			'image_url' => esc_url_raw( $image_url ),
		) );
	}

	/**
	 * Upscale an image.
	 *
	 * @param string $image_url The image URL to upscale.
	 * @param int    $scale     Scale factor (2 or 4).
	 * @return array|WP_Error Response array with upscaled image URL or WP_Error.
	 */
	public function upscale_image( string $image_url, int $scale = 2 ): array|WP_Error {
		return $this->post( '/v1/ai/upscale', array(
			'image_url' => esc_url_raw( $image_url ),
			'scale'     => min( max( $scale, 2 ), 4 ),
		) );
	}

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance data or WP_Error.
	 */
	public function get_balance(): array|WP_Error {
		return $this->get( '/v1/billing/balance' );
	}

	/**
	 * List available models.
	 *
	 * @return array|WP_Error Models list or WP_Error.
	 */
	public function list_models(): array|WP_Error {
		// Check transient cache first.
		$cached = get_transient( 'fotohub_ai_models_cache' );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->get( '/v1/models' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Cache for 1 hour.
		set_transient( 'fotohub_ai_models_cache', $response, HOUR_IN_SECONDS );

		return $response;
	}

	/**
	 * Edit an image with AI.
	 */
	public function edit_image(string $image_url, string $prompt, string $mode = 'edit', ?string $mask_url = null): array|WP_Error {
		$body = array(
			'image_url' => esc_url_raw($image_url),
			'prompt' => sanitize_text_field($prompt),
			'mode' => sanitize_text_field($mode),
		);
		if ($mask_url) {
			$body['mask_url'] = esc_url_raw($mask_url);
		}
		return $this->post('/v1/ai/edit/image', $body);
	}

	/**
	 * Generate a video.
	 */
	public function generate_video(string $prompt, array $options = []): array|WP_Error {
		$body = array(
			'prompt' => sanitize_text_field($prompt),
			'model' => $options['model'] ?? 'veo-2',
			'duration' => absint($options['duration'] ?? 4),
			'aspect_ratio' => sanitize_text_field($options['aspect_ratio'] ?? '16:9'),
		);
		if (!empty($options['image_url'])) {
			$body['image_url'] = esc_url_raw($options['image_url']);
		}
		if (!empty($options['resolution'])) {
			$body['resolution'] = sanitize_text_field($options['resolution']);
		}
		return $this->post('/v1/ai/generate/video', $body);
	}

	/**
	 * Generate music.
	 */
	public function generate_music(string $prompt, array $options = []): array|WP_Error {
		$body = array(
			'prompt' => sanitize_text_field($prompt),
			'model' => $options['model'] ?? 'stable-audio',
			'duration' => absint($options['duration'] ?? 30),
		);
		if (!empty($options['genre'])) $body['genre'] = sanitize_text_field($options['genre']);
		if (!empty($options['mood'])) $body['mood'] = sanitize_text_field($options['mood']);
		if (!empty($options['tempo'])) $body['tempo'] = absint($options['tempo']);
		if (isset($options['instrumental'])) $body['instrumental'] = (bool) $options['instrumental'];
		return $this->post('/v1/ai/generate/music', $body);
	}

	/**
	 * Generate sound effects.
	 */
	public function generate_sfx(string $prompt, int $duration = 5): array|WP_Error {
		return $this->post('/v1/ai/generate/sfx', array(
			'prompt' => sanitize_text_field($prompt),
			'duration' => min(max($duration, 1), 30),
		));
	}

	/**
	 * Generate speech from text.
	 */
	public function generate_speech(string $text, array $options = []): array|WP_Error {
		$body = array('text' => sanitize_text_field($text));
		if (!empty($options['voice_id'])) $body['voice_id'] = sanitize_text_field($options['voice_id']);
		if (!empty($options['model'])) $body['model'] = sanitize_text_field($options['model']);
		if (!empty($options['language'])) $body['language'] = sanitize_text_field($options['language']);
		if (!empty($options['speed'])) $body['speed'] = (float) $options['speed'];
		if (!empty($options['pitch'])) $body['pitch'] = (float) $options['pitch'];
		return $this->post('/v1/ai/generate/speech', $body);
	}

	/**
	 * Transcribe audio to text.
	 */
	public function transcribe(string $audio_url, string $language = 'auto'): array|WP_Error {
		return $this->post('/v1/ai/transcribe', array(
			'audio_url' => esc_url_raw($audio_url),
			'language' => sanitize_text_field($language),
		));
	}

	/**
	 * Chat completion.
	 */
	public function chat(array $messages, array $options = []): array|WP_Error {
		$body = array(
			'model' => $options['model'] ?? 'claude-sonnet-4-20250514',
			'messages' => $messages,
		);
		if (isset($options['temperature'])) $body['temperature'] = (float) $options['temperature'];
		if (isset($options['max_tokens'])) $body['max_tokens'] = absint($options['max_tokens']);
		return $this->post('/v1/ai/chat/completions', $body);
	}

	/**
	 * Analyze an image.
	 */
	public function analyze_image(string $image_url, array $features = []): array|WP_Error {
		$body = array('image_url' => esc_url_raw($image_url));
		if (!empty($features)) {
			$body['features'] = array_map('sanitize_text_field', $features);
		}
		return $this->post('/v1/ai/analyze/image', $body);
	}

	/**
	 * Enhance a prompt with AI.
	 */
	public function enhance_prompt(string $prompt, string $style = 'photographic'): array|WP_Error {
		return $this->post('/v1/ai/enhance-prompt', array(
			'prompt' => sanitize_text_field($prompt),
			'style' => sanitize_text_field($style),
		));
	}

	/**
	 * Run a Stability AI tool.
	 */
	public function stability_tool(string $tool_id, string $image_base64, array $options = []): array|WP_Error {
		$body = array(
			'image' => $image_base64,
			'output_format' => $options['output_format'] ?? 'png',
		);
		if (!empty($options['mask'])) $body['mask'] = $options['mask'];
		if (!empty($options['prompt'])) $body['prompt'] = sanitize_text_field($options['prompt']);
		if (!empty($options['reference'])) $body['reference'] = $options['reference'];
		if (!empty($options['padding'])) $body['padding'] = $options['padding'];
		if (!empty($options['search_prompt'])) $body['search_prompt'] = sanitize_text_field($options['search_prompt']);
		if (!empty($options['replace_prompt'])) $body['replace_prompt'] = sanitize_text_field($options['replace_prompt']);
		if (!empty($options['color'])) $body['color'] = sanitize_text_field($options['color']);
		return $this->post('/stability/' . sanitize_text_field($tool_id), $body);
	}

	/**
	 * Stability: upscale image.
	 */
	public function stability_upscale(string $image_base64, string $type = 'fast'): array|WP_Error {
		return $this->stability_tool($type . '-upscale', $image_base64);
	}

	/**
	 * Stability: remove background (high quality).
	 */
	public function stability_remove_bg(string $image_base64): array|WP_Error {
		return $this->stability_tool('remove-background', $image_base64);
	}

	/**
	 * Stability: erase object with mask.
	 */
	public function stability_erase(string $image_base64, string $mask_base64): array|WP_Error {
		return $this->stability_tool('erase', $image_base64, array('mask' => $mask_base64));
	}

	/**
	 * Stability: inpaint with mask and prompt.
	 */
	public function stability_inpaint(string $image_base64, string $mask_base64, string $prompt): array|WP_Error {
		return $this->stability_tool('inpaint', $image_base64, array(
			'mask' => $mask_base64,
			'prompt' => $prompt,
		));
	}

	/**
	 * Stability: outpaint (expand canvas).
	 */
	public function stability_outpaint(string $image_base64, array $padding): array|WP_Error {
		return $this->stability_tool('outpaint', $image_base64, array('padding' => $padding));
	}

	/**
	 * Stability: search and replace.
	 */
	public function stability_search_replace(string $image_base64, string $search, string $replace): array|WP_Error {
		return $this->stability_tool('search-and-replace', $image_base64, array(
			'search_prompt' => $search,
			'replace_prompt' => $replace,
		));
	}

	/**
	 * Stability: search and recolor.
	 */
	public function stability_recolor(string $image_base64, string $search, string $color): array|WP_Error {
		return $this->stability_tool('search-and-recolor', $image_base64, array(
			'search_prompt' => $search,
			'color' => $color,
		));
	}

	/**
	 * Stability: style transfer.
	 */
	public function stability_style_transfer(string $image_base64, string $reference_base64): array|WP_Error {
		return $this->stability_tool('style-transfer', $image_base64, array('reference' => $reference_base64));
	}

	/**
	 * Get billing balance.
	 * (Note: get_balance already exists but this adds caching)
	 */
	public function get_cached_balance(): array|WP_Error {
		$cached = get_transient('fotohub_ai_balance_cache');
		if (false !== $cached) {
			return $cached;
		}
		$response = $this->get_balance();
		if (!is_wp_error($response)) {
			set_transient('fotohub_ai_balance_cache', $response, 5 * MINUTE_IN_SECONDS);
		}
		return $response;
	}

	/**
	 * Get pricing information.
	 */
	public function get_pricing(): array|WP_Error {
		$cached = get_transient('fotohub_ai_pricing_cache');
		if (false !== $cached) {
			return $cached;
		}
		$response = $this->get('/v1/billing/pricing');
		if (!is_wp_error($response)) {
			set_transient('fotohub_ai_pricing_cache', $response, DAY_IN_SECONDS);
		}
		return $response;
	}

	/**
	 * Estimate cost for operations.
	 */
	public function estimate_cost(array $operations): array|WP_Error {
		return $this->post('/v1/billing/estimate', array('operations' => $operations));
	}

	/**
	 * Get transaction history.
	 */
	public function get_transactions(int $page = 1, int $page_size = 50): array|WP_Error {
		return $this->get('/v1/billing/transactions', array(
			'page' => $page,
			'page_size' => $page_size,
		));
	}

	/**
	 * Get available Stability tools.
	 */
	public function get_stability_tools(): array|WP_Error {
		$cached = get_transient('fotohub_stability_tools_cache');
		if (false !== $cached) {
			return $cached;
		}
		$response = $this->get('/stability/tools');
		if (!is_wp_error($response)) {
			set_transient('fotohub_stability_tools_cache', $response, DAY_IN_SECONDS);
		}
		return $response;
	}

	/**
	 * Test connection to the API.
	 *
	 * @return array|WP_Error Connection status or WP_Error.
	 */
	public function test_connection(): array|WP_Error {
		$response = $this->get_balance();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success' => true,
			'balance' => $response,
		);
	}

	/**
	 * Make a GET request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params   Query parameters.
	 * @return array|WP_Error Decoded response or WP_Error.
	 */
	private function get( string $endpoint, array $params = [] ): array|WP_Error {
		$url = $this->base_url . $endpoint;
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get( $url, array(
			'headers' => $this->get_headers(),
			'timeout' => $this->timeout,
		) );

		return $this->handle_response( $response );
	}

	/**
	 * Make a POST request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $body     Request body.
	 * @return array|WP_Error Decoded response or WP_Error.
	 */
	private function post( string $endpoint, array $body = [] ): array|WP_Error {
		$response = wp_remote_post( $this->base_url . $endpoint, array(
			'headers' => $this->get_headers(),
			'body'    => wp_json_encode( $body ),
			'timeout' => $this->timeout,
		) );

		return $this->handle_response( $response );
	}

	/**
	 * Get request headers.
	 */
	private function get_headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'User-Agent'    => 'FOTOhub-WordPress/' . FOTOHUB_AI_VERSION,
		);
	}

	/**
	 * Handle API response.
	 *
	 * @param array|WP_Error $response WordPress HTTP response.
	 * @return array|WP_Error Decoded body or WP_Error.
	 */
	private function handle_response( $response ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = $data['error'] ?? $data['message'] ?? __( 'Unknown API error', 'fotohub-ai' );
			return new WP_Error(
				'fotohub_api_error',
				sprintf( '[%d] %s', $code, $message ),
				array( 'status' => $code )
			);
		}

		if ( null === $data ) {
			return new WP_Error(
				'fotohub_parse_error',
				__( 'Failed to parse API response', 'fotohub-ai' )
			);
		}

		return $data;
	}
}
