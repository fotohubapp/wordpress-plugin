<?php
/**
 * Video Generation Modal template.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="fotohub-video-modal" class="fotohub-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="fotohub-video-modal-title">
	<div class="fotohub-modal-backdrop"></div>
	<div class="fotohub-modal-content">
		<div class="fotohub-modal-header">
			<h2 id="fotohub-video-modal-title">
				<span class="dashicons dashicons-video-alt3"></span>
				<?php esc_html_e( 'Generate Video with FOTOhub AI', 'fotohub-ai' ); ?>
			</h2>
			<button type="button" class="fotohub-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fotohub-ai' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="fotohub-modal-body">
			<div class="fotohub-form-group">
				<label for="fotohub-video-prompt"><?php esc_html_e( 'Prompt', 'fotohub-ai' ); ?> <span class="required">*</span></label>
				<textarea id="fotohub-video-prompt" rows="4" class="large-text"
						  placeholder="<?php esc_attr_e( 'Describe the video you want to generate. Include motion, camera angles, and scene details...', 'fotohub-ai' ); ?>"></textarea>
				<p class="description"><?php esc_html_e( 'Be specific about motion, camera movement, lighting, and scene transitions.', 'fotohub-ai' ); ?></p>
			</div>

			<div class="fotohub-form-row">
				<div class="fotohub-form-group fotohub-form-half">
					<label for="fotohub-video-model"><?php esc_html_e( 'Model', 'fotohub-ai' ); ?></label>
					<select id="fotohub-video-model">
						<option value="veo-3"><?php esc_html_e( 'Veo 3 (Best Quality)', 'fotohub-ai' ); ?></option>
						<option value="veo-2"><?php esc_html_e( 'Veo 2 (Fast)', 'fotohub-ai' ); ?></option>
						<option value="wan"><?php esc_html_e( 'Wan', 'fotohub-ai' ); ?></option>
						<option value="kling"><?php esc_html_e( 'Kling', 'fotohub-ai' ); ?></option>
						<option value="hailuo"><?php esc_html_e( 'Hailuo', 'fotohub-ai' ); ?></option>
						<option value="seedance"><?php esc_html_e( 'Seedance', 'fotohub-ai' ); ?></option>
						<option value="sora-2"><?php esc_html_e( 'Sora 2', 'fotohub-ai' ); ?></option>
					</select>
				</div>

				<div class="fotohub-form-group fotohub-form-half">
					<label for="fotohub-video-duration"><?php esc_html_e( 'Duration', 'fotohub-ai' ); ?></label>
					<select id="fotohub-video-duration">
						<option value="4"><?php esc_html_e( '4 seconds', 'fotohub-ai' ); ?></option>
						<option value="8" selected><?php esc_html_e( '8 seconds', 'fotohub-ai' ); ?></option>
						<option value="16"><?php esc_html_e( '16 seconds', 'fotohub-ai' ); ?></option>
					</select>
				</div>
			</div>

			<div class="fotohub-form-row">
				<div class="fotohub-form-group fotohub-form-half">
					<label for="fotohub-video-aspect"><?php esc_html_e( 'Aspect Ratio', 'fotohub-ai' ); ?></label>
					<select id="fotohub-video-aspect">
						<option value="16:9"><?php esc_html_e( '16:9 Landscape', 'fotohub-ai' ); ?></option>
						<option value="9:16"><?php esc_html_e( '9:16 Portrait', 'fotohub-ai' ); ?></option>
						<option value="1:1"><?php esc_html_e( '1:1 Square', 'fotohub-ai' ); ?></option>
					</select>
				</div>

				<div class="fotohub-form-group fotohub-form-half">
					<label><?php esc_html_e( 'Reference Image (optional)', 'fotohub-ai' ); ?></label>
					<div class="fotohub-media-selector">
						<button type="button" class="button" id="fotohub-video-ref-image-btn">
							<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Select Image', 'fotohub-ai' ); ?>
						</button>
						<span class="fotohub-selected-file" id="fotohub-video-ref-image-name"></span>
						<input type="hidden" id="fotohub-video-ref-image-url" value="">
						<button type="button" class="button fotohub-remove-ref-image" id="fotohub-video-ref-image-remove" style="display:none;">
							<span class="dashicons dashicons-no" style="vertical-align: middle;"></span>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Use an image as the first frame or style reference.', 'fotohub-ai' ); ?></p>
				</div>
			</div>

			<!-- Progress section -->
			<div id="fotohub-video-progress" class="fotohub-progress" style="display:none;">
				<div class="fotohub-progress-bar">
					<div class="fotohub-progress-fill fotohub-progress-indeterminate"></div>
				</div>
				<p class="fotohub-progress-text">
					<span class="dashicons dashicons-clock" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Video generation takes 1-5 minutes. Please wait...', 'fotohub-ai' ); ?>
				</p>
				<p class="fotohub-progress-subtext description">
					<?php esc_html_e( 'You can close this modal and check back later. The video will appear in your Media Library.', 'fotohub-ai' ); ?>
				</p>
				<div class="fotohub-polling-indicator">
					<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
					<span id="fotohub-video-poll-status"><?php esc_html_e( 'Checking status...', 'fotohub-ai' ); ?></span>
				</div>
			</div>

			<!-- Results section -->
			<div id="fotohub-video-results" class="fotohub-results" style="display:none;">
				<h3><?php esc_html_e( 'Generated Video', 'fotohub-ai' ); ?></h3>
				<div class="fotohub-video-player-wrap">
					<video id="fotohub-video-player" controls playsinline class="fotohub-video-player">
						<?php esc_html_e( 'Your browser does not support the video tag.', 'fotohub-ai' ); ?>
					</video>
				</div>
				<div class="fotohub-video-actions">
					<button type="button" class="button" id="fotohub-video-download">
						<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Download', 'fotohub-ai' ); ?>
					</button>
					<button type="button" class="button" id="fotohub-video-add-to-library">
						<span class="dashicons dashicons-admin-media" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Add to Media Library', 'fotohub-ai' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="fotohub-modal-footer">
			<div class="fotohub-modal-status" id="fotohub-video-modal-status"></div>
			<button type="button" class="button fotohub-modal-close">
				<?php esc_html_e( 'Close', 'fotohub-ai' ); ?>
			</button>
			<button type="button" class="button button-primary" id="fotohub-video-generate-submit">
				<span class="dashicons dashicons-video-alt3" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Generate Video', 'fotohub-ai' ); ?>
			</button>
		</div>
	</div>
</div>
