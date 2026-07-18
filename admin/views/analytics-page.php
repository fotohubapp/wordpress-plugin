<?php
/**
 * FOTOhub Analytics Dashboard page template.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap fotohub-analytics-wrap">
	<h1>
		<span class="dashicons dashicons-chart-area" style="font-size: 28px; margin-right: 8px; vertical-align: middle;"></span>
		<?php esc_html_e( 'FOTOhub Analytics', 'fotohub-ai' ); ?>
	</h1>

	<!-- Date Range Filter -->
	<div class="fotohub-analytics-filter">
		<label for="fotohub-analytics-range"><?php esc_html_e( 'Period:', 'fotohub-ai' ); ?></label>
		<select id="fotohub-analytics-range">
			<option value="7days"><?php esc_html_e( 'Last 7 Days', 'fotohub-ai' ); ?></option>
			<option value="this_month" selected><?php esc_html_e( 'This Month', 'fotohub-ai' ); ?></option>
			<option value="last_month"><?php esc_html_e( 'Last Month', 'fotohub-ai' ); ?></option>
			<option value="custom"><?php esc_html_e( 'Custom Range', 'fotohub-ai' ); ?></option>
		</select>

		<span class="fotohub-custom-range" id="fotohub-custom-range" style="display:none;">
			<input type="date" id="fotohub-analytics-from" class="fotohub-date-input">
			<span>&mdash;</span>
			<input type="date" id="fotohub-analytics-to" class="fotohub-date-input">
			<button type="button" class="button" id="fotohub-analytics-apply">
				<?php esc_html_e( 'Apply', 'fotohub-ai' ); ?>
			</button>
		</span>

		<button type="button" class="button" id="fotohub-analytics-export" style="float: right;">
			<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
			<?php esc_html_e( 'Export CSV', 'fotohub-ai' ); ?>
		</button>
	</div>

	<!-- Stat Cards -->
	<div class="fotohub-stats-row">
		<div class="fotohub-stat-card">
			<div class="fotohub-stat-icon">
				<span class="dashicons dashicons-images-alt2"></span>
			</div>
			<div class="fotohub-stat-content">
				<span class="fotohub-stat-value" id="fotohub-stat-total-generations">--</span>
				<span class="fotohub-stat-label"><?php esc_html_e( 'Total Generations', 'fotohub-ai' ); ?></span>
			</div>
		</div>

		<div class="fotohub-stat-card">
			<div class="fotohub-stat-icon">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div class="fotohub-stat-content">
				<span class="fotohub-stat-value" id="fotohub-stat-credits-used">--</span>
				<span class="fotohub-stat-label"><?php esc_html_e( 'Credits Used', 'fotohub-ai' ); ?></span>
			</div>
		</div>

		<div class="fotohub-stat-card">
			<div class="fotohub-stat-icon">
				<span class="dashicons dashicons-bank"></span>
			</div>
			<div class="fotohub-stat-content">
				<span class="fotohub-stat-value" id="fotohub-stat-credits-remaining">--</span>
				<span class="fotohub-stat-label"><?php esc_html_e( 'Credits Remaining', 'fotohub-ai' ); ?></span>
			</div>
		</div>

		<div class="fotohub-stat-card">
			<div class="fotohub-stat-icon">
				<span class="dashicons dashicons-calculator"></span>
			</div>
			<div class="fotohub-stat-content">
				<span class="fotohub-stat-value" id="fotohub-stat-avg-cost">--</span>
				<span class="fotohub-stat-label"><?php esc_html_e( 'Avg Cost/Generation', 'fotohub-ai' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Usage Over Time Chart -->
	<div class="fotohub-analytics-section">
		<h2><?php esc_html_e( 'Usage Over Time', 'fotohub-ai' ); ?></h2>
		<div class="fotohub-chart-container">
			<canvas id="fotohub-usage-chart" height="300"></canvas>
		</div>
	</div>

	<!-- Top Models Chart -->
	<div class="fotohub-analytics-section">
		<h2><?php esc_html_e( 'Top Models', 'fotohub-ai' ); ?></h2>
		<div class="fotohub-chart-container">
			<canvas id="fotohub-models-chart" height="200"></canvas>
		</div>
	</div>

	<!-- Recent Generations Table -->
	<div class="fotohub-analytics-section">
		<h2><?php esc_html_e( 'Recent Generations', 'fotohub-ai' ); ?></h2>
		<table class="widefat striped fotohub-analytics-table" id="fotohub-recent-generations-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Type', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Model', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Credits', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'fotohub-ai' ); ?></th>
				</tr>
			</thead>
			<tbody id="fotohub-recent-generations-body">
				<tr>
					<td colspan="5" class="fotohub-loading-row">
						<span class="spinner is-active" style="float: none;"></span>
						<?php esc_html_e( 'Loading...', 'fotohub-ai' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<?php if ( class_exists( 'WooCommerce' ) ) : ?>
	<!-- Top Products (WooCommerce) -->
	<div class="fotohub-analytics-section">
		<h2><?php esc_html_e( 'Top Products', 'fotohub-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Products with the most AI-generated images.', 'fotohub-ai' ); ?></p>
		<table class="widefat striped fotohub-analytics-table" id="fotohub-top-products-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Generations', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Credits Used', 'fotohub-ai' ); ?></th>
					<th><?php esc_html_e( 'Last Generated', 'fotohub-ai' ); ?></th>
				</tr>
			</thead>
			<tbody id="fotohub-top-products-body">
				<tr>
					<td colspan="4" class="fotohub-loading-row">
						<span class="spinner is-active" style="float: none;"></span>
						<?php esc_html_e( 'Loading...', 'fotohub-ai' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
