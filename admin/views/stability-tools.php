<?php
/**
 * Stability AI Tools page template.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stability_tools = array(
	array(
		'id'          => 'creative-upscale',
		'name'        => __( 'Creative Upscale', 'fotohub-ai' ),
		'description' => __( 'Upscale with AI enhancement and prompt guidance', 'fotohub-ai' ),
		'icon'        => 'dashicons-image-crop',
		'has_prompt'  => true,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'fast-upscale',
		'name'        => __( 'Fast Upscale', 'fotohub-ai' ),
		'description' => __( 'Quick 4x upscale, no prompt needed', 'fotohub-ai' ),
		'icon'        => 'dashicons-performance',
		'has_prompt'  => false,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'conservative-upscale',
		'name'        => __( 'Conservative Upscale', 'fotohub-ai' ),
		'description' => __( 'Upscale with minimal AI changes', 'fotohub-ai' ),
		'icon'        => 'dashicons-image-filter',
		'has_prompt'  => false,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'remove-background',
		'name'        => __( 'Remove Background', 'fotohub-ai' ),
		'description' => __( 'High-quality background removal', 'fotohub-ai' ),
		'icon'        => 'dashicons-scissors',
		'has_prompt'  => false,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'erase-object',
		'name'        => __( 'Erase Object', 'fotohub-ai' ),
		'description' => __( 'Remove objects by painting a mask', 'fotohub-ai' ),
		'icon'        => 'dashicons-editor-removeformatting',
		'has_prompt'  => false,
		'has_mask'    => true,
		'has_ref'     => false,
	),
	array(
		'id'          => 'inpaint',
		'name'        => __( 'Inpaint', 'fotohub-ai' ),
		'description' => __( 'Replace masked area with prompt-guided content', 'fotohub-ai' ),
		'icon'        => 'dashicons-art',
		'has_prompt'  => true,
		'has_mask'    => true,
		'has_ref'     => false,
	),
	array(
		'id'          => 'outpaint',
		'name'        => __( 'Outpaint', 'fotohub-ai' ),
		'description' => __( 'Expand image canvas in any direction', 'fotohub-ai' ),
		'icon'        => 'dashicons-editor-expand',
		'has_prompt'  => true,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'search-replace',
		'name'        => __( 'Search & Replace', 'fotohub-ai' ),
		'description' => __( 'Find and replace elements by text description', 'fotohub-ai' ),
		'icon'        => 'dashicons-search',
		'has_prompt'  => true,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'search-recolor',
		'name'        => __( 'Search & Recolor', 'fotohub-ai' ),
		'description' => __( 'Find elements and change their color', 'fotohub-ai' ),
		'icon'        => 'dashicons-admin-appearance',
		'has_prompt'  => true,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'style-transfer',
		'name'        => __( 'Style Transfer', 'fotohub-ai' ),
		'description' => __( "Apply another image's style", 'fotohub-ai' ),
		'icon'        => 'dashicons-admin-customizer',
		'has_prompt'  => false,
		'has_mask'    => false,
		'has_ref'     => true,
	),
	array(
		'id'          => 'control-sketch',
		'name'        => __( 'Control: Sketch', 'fotohub-ai' ),
		'description' => __( 'Generate from a sketch', 'fotohub-ai' ),
		'icon'        => 'dashicons-edit',
		'has_prompt'  => true,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'control-structure',
		'name'        => __( 'Control: Structure', 'fotohub-ai' ),
		'description' => __( 'Generate maintaining structural composition', 'fotohub-ai' ),
		'icon'        => 'dashicons-grid-view',
		'has_prompt'  => true,
		'has_mask'    => false,
		'has_ref'     => false,
	),
	array(
		'id'          => 'image-to-video',
		'name'        => __( 'Image to Video', 'fotohub-ai' ),
		'description' => __( 'Animate a still image', 'fotohub-ai' ),
		'icon'        => 'dashicons-video-alt3',
		'has_prompt'  => true,
		'has_mask'    => false,
		'has_ref'     => false,
	),
);
?>
<div class="wrap fotohub-stability-tools-wrap">
	<h1>
		<span class="dashicons dashicons-admin-generic" style="font-size: 28px; margin-right: 8px; vertical-align: middle;"></span>
		<?php esc_html_e( 'Stability AI Tools', 'fotohub-ai' ); ?>
	</h1>

	<p class="description" style="margin-bottom: 20px;">
		<?php esc_html_e( 'Professional image editing tools powered by Stability AI. Select a tool below to get started.', 'fotohub-ai' ); ?>
	</p>

	<!-- Tool Grid -->
	<div class="fotohub-tools-grid" id="fotohub-tools-grid">
		<?php foreach ( $stability_tools as $tool ) : ?>
			<div class="fotohub-tool-card" data-tool-id="<?php echo esc_attr( $tool['id'] ); ?>"
				 data-has-prompt="<?php echo esc_attr( $tool['has_prompt'] ? '1' : '0' ); ?>"
				 data-has-mask="<?php echo esc_attr( $tool['has_mask'] ? '1' : '0' ); ?>"
				 data-has-ref="<?php echo esc_attr( $tool['has_ref'] ? '1' : '0' ); ?>">
				<div class="fotohub-tool-card-icon">
					<span class="dashicons <?php echo esc_attr( $tool['icon'] ); ?>"></span>
				</div>
				<h3 class="fotohub-tool-card-title"><?php echo esc_html( $tool['name'] ); ?></h3>
				<p class="fotohub-tool-card-desc"><?php echo esc_html( $tool['description'] ); ?></p>
				<button type="button" class="button button-primary fotohub-tool-use-btn">
					<?php esc_html_e( 'Use Tool', 'fotohub-ai' ); ?>
				</button>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Tool Panel (shown when a tool is selected) -->
	<div class="fotohub-tool-panel" id="fotohub-tool-panel" style="display:none;">
		<div class="fotohub-tool-panel-header">
			<h2 id="fotohub-tool-panel-title"></h2>
			<button type="button" class="button" id="fotohub-tool-panel-back">
				<span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle;"></span>
				<?php esc_html_e( 'Back to Tools', 'fotohub-ai' ); ?>
			</button>
		</div>

		<div class="fotohub-tool-panel-body">
			<div class="fotohub-tool-panel-inputs">
				<!-- Image Selector -->
				<div class="fotohub-form-group">
					<label><?php esc_html_e( 'Source Image', 'fotohub-ai' ); ?> <span class="required">*</span></label>
					<div class="fotohub-media-selector">
						<button type="button" class="button" id="fotohub-stability-source-btn">
							<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Select from Media Library', 'fotohub-ai' ); ?>
						</button>
						<input type="hidden" id="fotohub-stability-source-url" value="">
					</div>
					<div class="fotohub-source-preview" id="fotohub-stability-source-preview" style="display:none;">
						<img id="fotohub-stability-source-img" src="" alt="">
						<button type="button" class="button fotohub-remove-source" id="fotohub-stability-remove-source">
							<span class="dashicons dashicons-no" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Remove', 'fotohub-ai' ); ?>
						</button>
					</div>
				</div>

				<!-- Mask Drawing Canvas (for Erase/Inpaint) -->
				<div class="fotohub-form-group fotohub-mask-section" id="fotohub-mask-section" style="display:none;">
					<label><?php esc_html_e( 'Mask', 'fotohub-ai' ); ?></label>
					<p class="description"><?php esc_html_e( 'Paint over the areas you want to modify. White = modify, black = keep.', 'fotohub-ai' ); ?></p>
					<div class="fotohub-mask-canvas-wrap">
						<canvas id="fotohub-mask-canvas" width="512" height="512"></canvas>
						<div class="fotohub-mask-controls">
							<label>
								<?php esc_html_e( 'Brush Size:', 'fotohub-ai' ); ?>
								<input type="range" id="fotohub-mask-brush-size" min="5" max="100" value="30">
								<span id="fotohub-mask-brush-size-label">30</span>px
							</label>
							<button type="button" class="button" id="fotohub-mask-clear">
								<?php esc_html_e( 'Clear Mask', 'fotohub-ai' ); ?>
							</button>
							<button type="button" class="button" id="fotohub-mask-invert">
								<?php esc_html_e( 'Invert', 'fotohub-ai' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Prompt Field -->
				<div class="fotohub-form-group fotohub-prompt-section" id="fotohub-stability-prompt-section" style="display:none;">
					<label for="fotohub-stability-prompt"><?php esc_html_e( 'Prompt', 'fotohub-ai' ); ?></label>
					<textarea id="fotohub-stability-prompt" rows="3" class="large-text"
							  placeholder="<?php esc_attr_e( 'Describe what you want...', 'fotohub-ai' ); ?>"></textarea>
				</div>

				<!-- Reference Image (for Style Transfer) -->
				<div class="fotohub-form-group fotohub-ref-section" id="fotohub-stability-ref-section" style="display:none;">
					<label><?php esc_html_e( 'Reference Image', 'fotohub-ai' ); ?></label>
					<div class="fotohub-media-selector">
						<button type="button" class="button" id="fotohub-stability-ref-btn">
							<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Select Reference', 'fotohub-ai' ); ?>
						</button>
						<input type="hidden" id="fotohub-stability-ref-url" value="">
					</div>
					<div class="fotohub-ref-preview" id="fotohub-stability-ref-preview" style="display:none;">
						<img id="fotohub-stability-ref-img" src="" alt="">
						<button type="button" class="button fotohub-remove-ref" id="fotohub-stability-remove-ref">
							<span class="dashicons dashicons-no" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Remove', 'fotohub-ai' ); ?>
						</button>
					</div>
				</div>

				<!-- Output Format -->
				<div class="fotohub-form-group">
					<label for="fotohub-stability-output-format"><?php esc_html_e( 'Output Format', 'fotohub-ai' ); ?></label>
					<select id="fotohub-stability-output-format">
						<option value="png"><?php esc_html_e( 'PNG (Lossless)', 'fotohub-ai' ); ?></option>
						<option value="webp"><?php esc_html_e( 'WebP (Optimized)', 'fotohub-ai' ); ?></option>
						<option value="jpeg"><?php esc_html_e( 'JPEG (Compressed)', 'fotohub-ai' ); ?></option>
					</select>
				</div>

				<!-- Submit -->
				<div class="fotohub-form-group">
					<button type="button" class="button button-primary button-hero" id="fotohub-stability-submit">
						<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Process Image', 'fotohub-ai' ); ?>
					</button>
				</div>

				<!-- Progress -->
				<div id="fotohub-stability-progress" class="fotohub-progress" style="display:none;">
					<div class="fotohub-progress-bar">
						<div class="fotohub-progress-fill fotohub-progress-indeterminate"></div>
					</div>
					<p class="fotohub-progress-text"><?php esc_html_e( 'Processing...', 'fotohub-ai' ); ?></p>
				</div>
			</div>

			<!-- Results: Before / After -->
			<div class="fotohub-tool-panel-results" id="fotohub-stability-results" style="display:none;">
				<h3><?php esc_html_e( 'Result', 'fotohub-ai' ); ?></h3>
				<div class="fotohub-before-after">
					<div class="fotohub-before">
						<h4><?php esc_html_e( 'Before', 'fotohub-ai' ); ?></h4>
						<img id="fotohub-stability-before-img" src="" alt="<?php esc_attr_e( 'Original', 'fotohub-ai' ); ?>">
					</div>
					<div class="fotohub-after">
						<h4><?php esc_html_e( 'After', 'fotohub-ai' ); ?></h4>
						<img id="fotohub-stability-after-img" src="" alt="<?php esc_attr_e( 'Processed', 'fotohub-ai' ); ?>">
					</div>
				</div>
				<div class="fotohub-result-actions">
					<button type="button" class="button" id="fotohub-stability-download">
						<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Download', 'fotohub-ai' ); ?>
					</button>
					<button type="button" class="button button-primary" id="fotohub-stability-save-to-library">
						<span class="dashicons dashicons-admin-media" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Save to Media Library', 'fotohub-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
