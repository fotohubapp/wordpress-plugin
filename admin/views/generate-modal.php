<?php
/**
 * AI Image Generation Modal template.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="fotohub-generate-modal" class="fotohub-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="fotohub-modal-title">
	<div class="fotohub-modal-backdrop"></div>
	<div class="fotohub-modal-content">
		<div class="fotohub-modal-header">
			<h2 id="fotohub-modal-title">
				<span class="dashicons dashicons-art"></span>
				<?php esc_html_e( 'Generate with FOTOhub AI', 'fotohub-ai' ); ?>
			</h2>
			<button type="button" class="fotohub-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fotohub-ai' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="fotohub-modal-body">
			<div class="fotohub-form-group">
				<label for="fotohub-prompt"><?php esc_html_e( 'Prompt', 'fotohub-ai' ); ?> <span class="required">*</span></label>
				<textarea id="fotohub-prompt" rows="3" class="large-text"
						  placeholder="<?php esc_attr_e( 'Describe the image you want to generate...', 'fotohub-ai' ); ?>"></textarea>
				<p class="description"><?php esc_html_e( 'Be descriptive. Include style, lighting, composition details.', 'fotohub-ai' ); ?></p>
			</div>

			<div class="fotohub-form-row">
				<div class="fotohub-form-group fotohub-form-half">
					<label for="fotohub-model"><?php esc_html_e( 'Model', 'fotohub-ai' ); ?></label>
					<select id="fotohub-model">
						<option value="seedream-5-0-260128"><?php esc_html_e( 'Seedream 5.0 (Recommended)', 'fotohub-ai' ); ?></option>
						<option value="flux-1-schnell"><?php esc_html_e( 'Flux 1 Schnell (Fast)', 'fotohub-ai' ); ?></option>
						<option value="flux-1-dev"><?php esc_html_e( 'Flux 1 Dev (Quality)', 'fotohub-ai' ); ?></option>
						<option value="stable-diffusion-xl"><?php esc_html_e( 'Stable Diffusion XL', 'fotohub-ai' ); ?></option>
						<option value="dall-e-3"><?php esc_html_e( 'DALL-E 3', 'fotohub-ai' ); ?></option>
					</select>
				</div>

				<div class="fotohub-form-group fotohub-form-half">
					<label for="fotohub-num-images"><?php esc_html_e( 'Number of Images', 'fotohub-ai' ); ?></label>
					<select id="fotohub-num-images">
						<option value="1">1</option>
						<option value="2">2</option>
						<option value="3">3</option>
						<option value="4">4</option>
					</select>
				</div>
			</div>

			<div class="fotohub-form-row">
				<div class="fotohub-form-group fotohub-form-half">
					<label for="fotohub-aspect"><?php esc_html_e( 'Aspect Ratio', 'fotohub-ai' ); ?></label>
					<select id="fotohub-aspect">
						<option value="1:1" data-width="1024" data-height="1024"><?php esc_html_e( '1:1 Square (1024x1024)', 'fotohub-ai' ); ?></option>
						<option value="16:9" data-width="1536" data-height="864"><?php esc_html_e( '16:9 Landscape (1536x864)', 'fotohub-ai' ); ?></option>
						<option value="9:16" data-width="864" data-height="1536"><?php esc_html_e( '9:16 Portrait (864x1536)', 'fotohub-ai' ); ?></option>
						<option value="4:3" data-width="1365" data-height="1024"><?php esc_html_e( '4:3 Standard (1365x1024)', 'fotohub-ai' ); ?></option>
						<option value="3:4" data-width="1024" data-height="1365"><?php esc_html_e( '3:4 Portrait (1024x1365)', 'fotohub-ai' ); ?></option>
						<option value="custom" data-width="0" data-height="0"><?php esc_html_e( 'Custom', 'fotohub-ai' ); ?></option>
					</select>
				</div>

				<div class="fotohub-form-group fotohub-form-half fotohub-custom-dims" style="display:none;">
					<label><?php esc_html_e( 'Dimensions', 'fotohub-ai' ); ?></label>
					<div class="fotohub-dims-inputs">
						<input type="number" id="fotohub-width" min="256" max="2048" step="64"
							   value="<?php echo esc_attr( get_option( 'fotohub_ai_default_width', 1024 ) ); ?>"
							   placeholder="<?php esc_attr_e( 'Width', 'fotohub-ai' ); ?>">
						<span>x</span>
						<input type="number" id="fotohub-height" min="256" max="2048" step="64"
							   value="<?php echo esc_attr( get_option( 'fotohub_ai_default_height', 1024 ) ); ?>"
							   placeholder="<?php esc_attr_e( 'Height', 'fotohub-ai' ); ?>">
					</div>
				</div>
			</div>

			<div class="fotohub-form-group">
				<label for="fotohub-negative-prompt"><?php esc_html_e( 'Negative Prompt (optional)', 'fotohub-ai' ); ?></label>
				<input type="text" id="fotohub-negative-prompt" class="large-text"
					   placeholder="<?php esc_attr_e( 'Things to avoid: blurry, low quality, watermark...', 'fotohub-ai' ); ?>">
			</div>

			<!-- Results area -->
			<div id="fotohub-generate-results" class="fotohub-results" style="display:none;">
				<h3><?php esc_html_e( 'Generated Images', 'fotohub-ai' ); ?></h3>
				<div class="fotohub-results-grid"></div>
			</div>

			<!-- Progress indicator -->
			<div id="fotohub-generate-progress" class="fotohub-progress" style="display:none;">
				<div class="fotohub-progress-bar">
					<div class="fotohub-progress-fill"></div>
				</div>
				<p class="fotohub-progress-text"><?php esc_html_e( 'Generating your images...', 'fotohub-ai' ); ?></p>
			</div>
		</div>

		<div class="fotohub-modal-footer">
			<div class="fotohub-modal-status" id="fotohub-modal-status"></div>
			<button type="button" class="button fotohub-modal-close">
				<?php esc_html_e( 'Close', 'fotohub-ai' ); ?>
			</button>
			<button type="button" class="button button-primary" id="fotohub-generate-submit">
				<span class="dashicons dashicons-art" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Generate', 'fotohub-ai' ); ?>
			</button>
		</div>
	</div>
</div>
