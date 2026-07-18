<?php
/**
 * Bulk generation page template.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap fotohub-bulk-wrap">
	<h1>
		<span class="dashicons dashicons-images-alt2" style="font-size: 28px; margin-right: 8px; vertical-align: middle;"></span>
		<?php esc_html_e( 'FOTOhub Bulk Generate', 'fotohub-ai' ); ?>
	</h1>

	<?php if ( ! Fotohub_API::get_api_key() ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: settings page link */
					esc_html__( 'Please configure your API key in %s first.', 'fotohub-ai' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=fotohub-ai-settings' ) ) . '">' . esc_html__( 'Settings', 'fotohub-ai' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php else : ?>

	<div class="fotohub-bulk-container">
		<div class="fotohub-bulk-input">
			<h2><?php esc_html_e( 'Input Prompts', 'fotohub-ai' ); ?></h2>

			<div class="fotohub-bulk-tabs">
				<button type="button" class="fotohub-tab active" data-tab="manual">
					<?php esc_html_e( 'Manual Entry', 'fotohub-ai' ); ?>
				</button>
				<button type="button" class="fotohub-tab" data-tab="csv">
					<?php esc_html_e( 'CSV Upload', 'fotohub-ai' ); ?>
				</button>
			</div>

			<!-- Manual entry tab -->
			<div class="fotohub-tab-content active" data-tab="manual">
				<p class="description">
					<?php esc_html_e( 'Enter one prompt per line. Each prompt will generate one image.', 'fotohub-ai' ); ?>
				</p>
				<textarea id="fotohub-bulk-prompts" rows="10" class="large-text"
						  placeholder="<?php esc_attr_e( "A professional headshot of a businesswoman\nMinimalist product photo of white sneakers\nAbstract colorful background pattern", 'fotohub-ai' ); ?>"></textarea>
				<p class="fotohub-prompt-count">
					<span id="fotohub-prompt-count">0</span> <?php esc_html_e( 'prompts', 'fotohub-ai' ); ?>
				</p>
			</div>

			<!-- CSV upload tab -->
			<div class="fotohub-tab-content" data-tab="csv">
				<p class="description">
					<?php esc_html_e( 'Upload a CSV file with one prompt per row. First column will be used as the prompt.', 'fotohub-ai' ); ?>
				</p>
				<input type="file" id="fotohub-bulk-csv" accept=".csv,.txt">
				<div id="fotohub-csv-preview" class="fotohub-csv-preview" style="display:none;">
					<h4><?php esc_html_e( 'Preview:', 'fotohub-ai' ); ?></h4>
					<ul></ul>
				</div>
			</div>

			<!-- Options -->
			<div class="fotohub-bulk-options">
				<h3><?php esc_html_e( 'Generation Options', 'fotohub-ai' ); ?></h3>

				<div class="fotohub-form-row">
					<div class="fotohub-form-group fotohub-form-half">
						<label for="fotohub-bulk-model"><?php esc_html_e( 'Model', 'fotohub-ai' ); ?></label>
						<select id="fotohub-bulk-model">
							<option value="seedream-5-0-260128"><?php esc_html_e( 'Seedream 5.0 (Recommended)', 'fotohub-ai' ); ?></option>
							<option value="flux-1-schnell"><?php esc_html_e( 'Flux 1 Schnell (Fast)', 'fotohub-ai' ); ?></option>
							<option value="flux-1-dev"><?php esc_html_e( 'Flux 1 Dev (Quality)', 'fotohub-ai' ); ?></option>
							<option value="stable-diffusion-xl"><?php esc_html_e( 'Stable Diffusion XL', 'fotohub-ai' ); ?></option>
							<option value="dall-e-3"><?php esc_html_e( 'DALL-E 3', 'fotohub-ai' ); ?></option>
						</select>
					</div>

					<div class="fotohub-form-group fotohub-form-half">
						<label for="fotohub-bulk-dimensions"><?php esc_html_e( 'Dimensions', 'fotohub-ai' ); ?></label>
						<select id="fotohub-bulk-dimensions">
							<option value="1024x1024"><?php esc_html_e( '1024x1024 (Square)', 'fotohub-ai' ); ?></option>
							<option value="1536x864"><?php esc_html_e( '1536x864 (Landscape)', 'fotohub-ai' ); ?></option>
							<option value="864x1536"><?php esc_html_e( '864x1536 (Portrait)', 'fotohub-ai' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<div class="fotohub-bulk-actions">
				<button type="button" class="button button-primary button-hero" id="fotohub-bulk-start">
					<span class="dashicons dashicons-art" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Start Bulk Generation', 'fotohub-ai' ); ?>
				</button>
			</div>
		</div>

		<!-- Progress and results -->
		<div class="fotohub-bulk-output" style="display:none;">
			<h2><?php esc_html_e( 'Progress', 'fotohub-ai' ); ?></h2>

			<div class="fotohub-bulk-progress">
				<div class="fotohub-progress-bar">
					<div class="fotohub-progress-fill" id="fotohub-bulk-progress-fill"></div>
				</div>
				<p class="fotohub-progress-text" id="fotohub-bulk-progress-text">
					<?php esc_html_e( 'Starting...', 'fotohub-ai' ); ?>
				</p>
			</div>

			<div class="fotohub-bulk-results" id="fotohub-bulk-results">
				<h3><?php esc_html_e( 'Results', 'fotohub-ai' ); ?></h3>
				<div class="fotohub-results-grid"></div>
			</div>

			<div class="fotohub-bulk-errors" id="fotohub-bulk-errors" style="display:none;">
				<h3><?php esc_html_e( 'Errors', 'fotohub-ai' ); ?></h3>
				<ul></ul>
			</div>
		</div>
	</div>

	<?php endif; ?>
</div>
