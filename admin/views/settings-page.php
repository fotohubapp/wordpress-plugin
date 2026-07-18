<?php
/**
 * Settings page template.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap fotohub-settings-wrap">
	<h1>
		<span class="dashicons dashicons-format-image" style="font-size: 28px; margin-right: 8px; vertical-align: middle;"></span>
		<?php esc_html_e( 'FOTOhub AI Settings', 'fotohub-ai' ); ?>
	</h1>

	<div class="fotohub-settings-header">
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to FOTOhub platform */
				esc_html__( 'Connect your WordPress site to %s for AI-powered image generation, background removal, and upscaling.', 'fotohub-ai' ),
				'<a href="https://fotohub.app" target="_blank">FOTOhub</a>'
			);
			?>
		</p>
	</div>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'fotohub_ai_settings' );
		do_settings_sections( 'fotohub-ai-settings' );
		submit_button();
		?>
	</form>

	<hr>

	<div class="fotohub-settings-info">
		<h2><?php esc_html_e( 'Quick Start', 'fotohub-ai' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Enter your API key above and click "Test Connection" to verify.', 'fotohub-ai' ); ?></li>
			<li>
				<?php
				printf(
					/* translators: %s: link to media library */
					esc_html__( 'Go to %s and click "Generate with AI" to create your first image.', 'fotohub-ai' ),
					'<a href="' . esc_url( admin_url( 'upload.php' ) ) . '">' . esc_html__( 'Media Library', 'fotohub-ai' ) . '</a>'
				);
				?>
			</li>
			<li>
				<?php
				printf(
					/* translators: %s: link to bulk tool */
					esc_html__( 'Use %s for batch image generation from prompts or CSV.', 'fotohub-ai' ),
					'<a href="' . esc_url( admin_url( 'tools.php?page=fotohub-ai-bulk' ) ) . '">' . esc_html__( 'Tools > FOTOhub Bulk', 'fotohub-ai' ) . '</a>'
				);
				?>
			</li>
		</ol>

		<h3><?php esc_html_e( 'Available Features', 'fotohub-ai' ); ?></h3>
		<table class="widefat fotohub-features-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Feature', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Location', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Description', 'fotohub-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'AI Image Generation', 'fotohub-ai' ); ?></strong></td>
					<td><?php esc_html_e( 'Media > Add New', 'fotohub-ai' ); ?></td>
					<td><?php esc_html_e( 'Generate images from text prompts using AI models.', 'fotohub-ai' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Background Removal', 'fotohub-ai' ); ?></strong></td>
					<td><?php esc_html_e( 'Media Library (row action)', 'fotohub-ai' ); ?></td>
					<td><?php esc_html_e( 'Remove backgrounds from existing images.', 'fotohub-ai' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Image Upscaling', 'fotohub-ai' ); ?></strong></td>
					<td><?php esc_html_e( 'Media Library (row action)', 'fotohub-ai' ); ?></td>
					<td><?php esc_html_e( 'Upscale images 2x or 4x with AI enhancement.', 'fotohub-ai' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Bulk Generation', 'fotohub-ai' ); ?></strong></td>
					<td><?php esc_html_e( 'Tools > FOTOhub Bulk', 'fotohub-ai' ); ?></td>
					<td><?php esc_html_e( 'Generate multiple images from a list of prompts.', 'fotohub-ai' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WooCommerce Photos', 'fotohub-ai' ); ?></strong></td>
					<td><?php esc_html_e( 'Product Edit > FOTOhub AI tab', 'fotohub-ai' ); ?></td>
					<td><?php esc_html_e( 'Generate product images from title and description.', 'fotohub-ai' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
