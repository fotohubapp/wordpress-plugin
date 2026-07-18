<?php
/**
 * FOTOhub Analytics Dashboard.
 *
 * Tracks API usage, displays stats on the WP Dashboard, and provides
 * a dedicated analytics page with credit balance, cost breakdown, and export.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics dashboard class.
 */
class Fotohub_Analytics {

	/**
	 * Custom table name (without prefix).
	 */
	private const TABLE_NAME = 'fotohub_usage';

	/**
	 * Transient key for cached balance.
	 */
	private const BALANCE_TRANSIENT = 'fotohub_ai_balance';

	/**
	 * Balance cache TTL in seconds (5 minutes).
	 */
	private const BALANCE_TTL = 300;

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_dashboard_widget' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_csv_export' ) );

		// Hook into API calls to log usage.
		add_action( 'fotohub_api_request_completed', array( __CLASS__, 'log_api_call' ), 10, 4 );
		add_filter( 'fotohub_api_post_response', array( __CLASS__, 'filter_log_response' ), 10, 3 );
	}

	/**
	 * Create the custom database table on plugin activation.
	 */
	public static function install_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			endpoint varchar(255) NOT NULL,
			category varchar(50) NOT NULL DEFAULT 'other',
			model varchar(100) DEFAULT NULL,
			credits_used decimal(10,4) NOT NULL DEFAULT 0,
			request_data longtext DEFAULT NULL,
			response_status smallint(5) NOT NULL DEFAULT 200,
			duration_ms int(11) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned DEFAULT NULL,
			product_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user_id (user_id),
			KEY idx_category (category),
			KEY idx_model (model),
			KEY idx_created_at (created_at),
			KEY idx_product_id (product_id),
			KEY idx_category_created (category, created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'fotohub_ai_db_version', '1.0.0' );
	}

	/**
	 * Register the WP Dashboard widget.
	 */
	public static function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'fotohub_ai_usage_widget',
			__( 'FOTOhub AI Usage', 'fotohub-ai' ),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the Dashboard widget content.
	 */
	public static function render_dashboard_widget(): void {
		if ( ! Fotohub_API::get_api_key() ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'Configure your API key in', 'fotohub-ai' ),
				esc_url( admin_url( 'options-general.php?page=fotohub-ai-settings' ) ),
				esc_html__( 'FOTOhub Settings', 'fotohub-ai' )
			);
			return;
		}

		$stats   = self::get_monthly_stats();
		$balance = self::get_cached_balance();
		?>
		<div class="fotohub-dashboard-widget">
			<?php if ( ! is_wp_error( $balance ) ) : ?>
				<div class="fotohub-balance-summary" style="margin-bottom: 12px; padding: 10px; background: #f0f6fc; border-radius: 4px;">
					<strong><?php esc_html_e( 'Credits Remaining:', 'fotohub-ai' ); ?></strong>
					<span style="float: right; font-size: 1.2em; font-weight: bold; color: #1d4ed8;">
						<?php echo esc_html( number_format( $balance['credits'] ?? 0, 2 ) ); ?>
					</span>
				</div>
			<?php endif; ?>

			<table class="widefat striped" style="border: none;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Category', 'fotohub-ai' ); ?></th>
						<th style="text-align: right;"><?php esc_html_e( 'This Month', 'fotohub-ai' ); ?></th>
						<th style="text-align: right;"><?php esc_html_e( 'Credits', 'fotohub-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stats['by_category'] as $category => $data ) : ?>
						<tr>
							<td><?php echo esc_html( ucfirst( $category ) ); ?></td>
							<td style="text-align: right;"><?php echo esc_html( number_format( $data['count'] ) ); ?></td>
							<td style="text-align: right;"><?php echo esc_html( number_format( $data['credits'], 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $stats['by_category'] ) ) : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'No usage data yet.', 'fotohub-ai' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<th><strong><?php esc_html_e( 'Total', 'fotohub-ai' ); ?></strong></th>
						<th style="text-align: right;"><strong><?php echo esc_html( number_format( $stats['total_count'] ) ); ?></strong></th>
						<th style="text-align: right;"><strong><?php echo esc_html( number_format( $stats['total_credits'], 2 ) ); ?></strong></th>
					</tr>
				</tfoot>
			</table>

			<p style="margin-top: 10px; text-align: right;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fotohub-ai-analytics' ) ); ?>" class="button button-small">
					<?php esc_html_e( 'View Full Analytics', 'fotohub-ai' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Register the Analytics admin page under FOTOhub menu.
	 */
	public static function register_admin_page(): void {
		add_submenu_page(
			'options-general.php',
			__( 'FOTOhub Analytics', 'fotohub-ai' ),
			__( 'FOTOhub Analytics', 'fotohub-ai' ),
			'manage_options',
			'fotohub-ai-analytics',
			array( __CLASS__, 'render_analytics_page' )
		);
	}

	/**
	 * Enqueue admin assets for the analytics page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'settings_page_fotohub-ai-analytics' !== $hook && 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'fotohub-ai-analytics',
			FOTOHUB_AI_PLUGIN_URL . 'admin/css/analytics.css',
			array(),
			FOTOHUB_AI_VERSION
		);

		if ( 'settings_page_fotohub-ai-analytics' === $hook ) {
			wp_enqueue_script(
				'fotohub-ai-chart',
				FOTOHUB_AI_PLUGIN_URL . 'admin/js/chart.min.js',
				array(),
				'4.4.0',
				true
			);

			wp_enqueue_script(
				'fotohub-ai-analytics',
				FOTOHUB_AI_PLUGIN_URL . 'admin/js/analytics.js',
				array( 'fotohub-ai-chart', 'jquery' ),
				FOTOHUB_AI_VERSION,
				true
			);

			$model_breakdown = self::get_model_breakdown();
			wp_localize_script( 'fotohub-ai-analytics', 'fotohubAnalytics', array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'fotohub_ai_analytics_nonce' ),
				'modelBreakdown' => $model_breakdown,
				'i18n'           => array(
					'usageByModel' => __( 'Usage by Model', 'fotohub-ai' ),
					'credits'      => __( 'Credits', 'fotohub-ai' ),
					'generations'  => __( 'Generations', 'fotohub-ai' ),
				),
			) );
		}
	}

	/**
	 * Render the full analytics page.
	 */
	public static function render_analytics_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'fotohub-ai' ) );
		}

		$stats          = self::get_monthly_stats();
		$balance        = self::get_cached_balance();
		$model_data     = self::get_model_breakdown();
		$top_products   = self::get_top_products( 10 );
		$transactions   = self::get_recent_transactions();
		?>
		<div class="wrap fotohub-analytics-wrap">
			<h1><?php esc_html_e( 'FOTOhub AI Analytics', 'fotohub-ai' ); ?></h1>

			<!-- Balance Overview -->
			<div class="fotohub-analytics-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0;">
				<div class="fotohub-card" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
					<h3 style="margin: 0 0 8px; color: #1d4ed8;"><?php esc_html_e( 'Credit Balance', 'fotohub-ai' ); ?></h3>
					<p style="font-size: 2em; margin: 0; font-weight: bold;">
						<?php
						if ( ! is_wp_error( $balance ) ) {
							echo esc_html( number_format( $balance['credits'] ?? 0, 2 ) );
						} else {
							esc_html_e( 'N/A', 'fotohub-ai' );
						}
						?>
					</p>
				</div>

				<div class="fotohub-card" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
					<h3 style="margin: 0 0 8px; color: #16a34a;"><?php esc_html_e( 'Generations This Month', 'fotohub-ai' ); ?></h3>
					<p style="font-size: 2em; margin: 0; font-weight: bold;">
						<?php echo esc_html( number_format( $stats['total_count'] ) ); ?>
					</p>
				</div>

				<div class="fotohub-card" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
					<h3 style="margin: 0 0 8px; color: #dc2626;"><?php esc_html_e( 'Credits Used This Month', 'fotohub-ai' ); ?></h3>
					<p style="font-size: 2em; margin: 0; font-weight: bold;">
						<?php echo esc_html( number_format( $stats['total_credits'], 2 ) ); ?>
					</p>
				</div>

				<div class="fotohub-card" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
					<h3 style="margin: 0 0 8px; color: #9333ea;"><?php esc_html_e( 'Avg Credits/Generation', 'fotohub-ai' ); ?></h3>
					<p style="font-size: 2em; margin: 0; font-weight: bold;">
						<?php
						$avg = $stats['total_count'] > 0 ? $stats['total_credits'] / $stats['total_count'] : 0;
						echo esc_html( number_format( $avg, 3 ) );
						?>
					</p>
				</div>
			</div>

			<!-- Category Breakdown -->
			<div class="fotohub-analytics-section" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
				<div style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
					<h2><?php esc_html_e( 'Usage by Category', 'fotohub-ai' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Category', 'fotohub-ai' ); ?></th>
								<th style="text-align: right;"><?php esc_html_e( 'Count', 'fotohub-ai' ); ?></th>
								<th style="text-align: right;"><?php esc_html_e( 'Credits', 'fotohub-ai' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stats['by_category'] as $category => $data ) : ?>
								<tr>
									<td><?php echo esc_html( ucfirst( $category ) ); ?></td>
									<td style="text-align: right;"><?php echo esc_html( number_format( $data['count'] ) ); ?></td>
									<td style="text-align: right;"><?php echo esc_html( number_format( $data['credits'], 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $stats['by_category'] ) ) : ?>
								<tr>
									<td colspan="3"><?php esc_html_e( 'No data available.', 'fotohub-ai' ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
					<h2><?php esc_html_e( 'Most Used Models', 'fotohub-ai' ); ?></h2>
					<canvas id="fotohub-model-chart" width="400" height="300"></canvas>
					<?php if ( empty( $model_data ) ) : ?>
						<p><?php esc_html_e( 'No model usage data yet.', 'fotohub-ai' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Top Products -->
			<?php if ( class_exists( 'WooCommerce' ) && ! empty( $top_products ) ) : ?>
				<div style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0;">
					<h2><?php esc_html_e( 'Top Products by Generation Count', 'fotohub-ai' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Product', 'fotohub-ai' ); ?></th>
								<th style="text-align: right;"><?php esc_html_e( 'Generations', 'fotohub-ai' ); ?></th>
								<th style="text-align: right;"><?php esc_html_e( 'Credits Used', 'fotohub-ai' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $top_products as $product ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $product->product_id ) ); ?>">
											<?php echo esc_html( $product->product_title ); ?>
										</a>
									</td>
									<td style="text-align: right;"><?php echo esc_html( number_format( $product->generation_count ) ); ?></td>
									<td style="text-align: right;"><?php echo esc_html( number_format( $product->total_credits, 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<!-- Recent Transactions -->
			<?php if ( ! is_wp_error( $transactions ) && ! empty( $transactions ) ) : ?>
				<div style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0;">
					<h2><?php esc_html_e( 'Recent API Transactions', 'fotohub-ai' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'fotohub-ai' ); ?></th>
								<th><?php esc_html_e( 'Type', 'fotohub-ai' ); ?></th>
								<th><?php esc_html_e( 'Description', 'fotohub-ai' ); ?></th>
								<th style="text-align: right;"><?php esc_html_e( 'Credits', 'fotohub-ai' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $transactions, 0, 20 ) as $tx ) : ?>
								<tr>
									<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $tx['created_at'] ?? $tx['date'] ?? '' ) ) ); ?></td>
									<td><?php echo esc_html( ucfirst( $tx['type'] ?? 'unknown' ) ); ?></td>
									<td><?php echo esc_html( $tx['description'] ?? $tx['memo'] ?? '-' ); ?></td>
									<td style="text-align: right;"><?php echo esc_html( number_format( $tx['amount'] ?? $tx['credits'] ?? 0, 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<!-- Export -->
			<div style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0;">
				<h2><?php esc_html_e( 'Export', 'fotohub-ai' ); ?></h2>
				<p><?php esc_html_e( 'Download your complete generation history as a CSV file.', 'fotohub-ai' ); ?></p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=fotohub-ai-analytics&action=export_csv' ), 'fotohub_export_csv' ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Export CSV', 'fotohub-ai' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Log an API call to the usage table.
	 *
	 * @param string $endpoint    The API endpoint called.
	 * @param array  $request     The request body.
	 * @param array  $response    The response data.
	 * @param int    $duration_ms Request duration in milliseconds.
	 */
	public static function log_api_call( string $endpoint, array $request, array $response, int $duration_ms = 0 ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Determine category from endpoint.
		$category = self::categorize_endpoint( $endpoint );

		// Extract model if present.
		$model = $request['model'] ?? null;

		// Extract credits used from response if available.
		$credits_used = $response['credits_used'] ?? $response['cost'] ?? 0;

		// Determine response status.
		$status = isset( $response['error'] ) ? 400 : 200;

		// Get associated post/product ID from context.
		$post_id    = $request['_post_id'] ?? null;
		$product_id = $request['_product_id'] ?? null;

		$wpdb->insert(
			$table_name,
			array(
				'user_id'         => get_current_user_id(),
				'endpoint'        => sanitize_text_field( $endpoint ),
				'category'        => $category,
				'model'           => $model ? sanitize_text_field( $model ) : null,
				'credits_used'    => floatval( $credits_used ),
				'request_data'    => wp_json_encode( self::sanitize_request_for_log( $request ) ),
				'response_status' => intval( $status ),
				'duration_ms'     => absint( $duration_ms ),
				'post_id'         => $post_id ? absint( $post_id ) : null,
				'product_id'      => $product_id ? absint( $product_id ) : null,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Filter hook to log API responses passively.
	 *
	 * @param array  $response The API response.
	 * @param string $endpoint The endpoint called.
	 * @param array  $body     The request body.
	 * @return array Unmodified response (pass-through).
	 */
	public static function filter_log_response( array $response, string $endpoint, array $body ): array {
		self::log_api_call( $endpoint, $body, $response, 0 );
		return $response;
	}

	/**
	 * Get aggregated monthly stats.
	 *
	 * @return array Associative array with total_count, total_credits, and by_category breakdown.
	 */
	public static function get_monthly_stats(): array {
		global $wpdb;

		$table_name  = $wpdb->prefix . self::TABLE_NAME;
		$month_start = gmdate( 'Y-m-01 00:00:00' );

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( ! $table_exists ) {
			return array(
				'total_count'   => 0,
				'total_credits' => 0.0,
				'by_category'   => array(),
			);
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT category, COUNT(*) as count, SUM(credits_used) as credits
				 FROM {$table_name}
				 WHERE created_at >= %s AND response_status < 400
				 GROUP BY category
				 ORDER BY credits DESC",
				$month_start
			)
		);

		$by_category   = array();
		$total_count   = 0;
		$total_credits = 0.0;

		foreach ( $results as $row ) {
			$by_category[ $row->category ] = array(
				'count'   => (int) $row->count,
				'credits' => (float) $row->credits,
			);
			$total_count   += (int) $row->count;
			$total_credits += (float) $row->credits;
		}

		return array(
			'total_count'   => $total_count,
			'total_credits' => $total_credits,
			'by_category'   => $by_category,
		);
	}

	/**
	 * Get model usage breakdown for chart data.
	 *
	 * @return array Array of objects with model, count, and credits fields.
	 */
	public static function get_model_breakdown(): array {
		global $wpdb;

		$table_name  = $wpdb->prefix . self::TABLE_NAME;
		$month_start = gmdate( 'Y-m-01 00:00:00' );

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( ! $table_exists ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT model, COUNT(*) as count, SUM(credits_used) as credits
				 FROM {$table_name}
				 WHERE created_at >= %s AND model IS NOT NULL AND response_status < 400
				 GROUP BY model
				 ORDER BY count DESC
				 LIMIT 10",
				$month_start
			)
		);

		$data = array();
		foreach ( $results as $row ) {
			$data[] = array(
				'model'   => $row->model,
				'count'   => (int) $row->count,
				'credits' => (float) $row->credits,
			);
		}

		return $data;
	}

	/**
	 * Get top products by generation count (WooCommerce).
	 *
	 * @param int $limit Maximum number of products to return.
	 * @return array Array of product stats objects.
	 */
	public static function get_top_products( int $limit = 10 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( ! $table_exists ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.product_id, p.post_title as product_title,
				        COUNT(*) as generation_count, SUM(u.credits_used) as total_credits
				 FROM {$table_name} u
				 INNER JOIN {$wpdb->posts} p ON u.product_id = p.ID
				 WHERE u.product_id IS NOT NULL AND u.response_status < 400
				 GROUP BY u.product_id, p.post_title
				 ORDER BY generation_count DESC
				 LIMIT %d",
				$limit
			)
		);

		return $results ?: array();
	}

	/**
	 * Export generation history as CSV.
	 */
	public static function export_csv(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( ! $table_exists ) {
			wp_die( esc_html__( 'No usage data to export.', 'fotohub-ai' ) );
		}

		$results = $wpdb->get_results(
			"SELECT id, user_id, endpoint, category, model, credits_used,
			        response_status, duration_ms, post_id, product_id, created_at
			 FROM {$table_name}
			 ORDER BY created_at DESC
			 LIMIT 10000",
			ARRAY_A
		);

		if ( empty( $results ) ) {
			wp_die( esc_html__( 'No usage data to export.', 'fotohub-ai' ) );
		}

		$filename = sprintf( 'fotohub-usage-export-%s.csv', gmdate( 'Y-m-d' ) );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// Write header row.
		fputcsv( $output, array(
			'ID', 'User ID', 'Endpoint', 'Category', 'Model',
			'Credits Used', 'Status', 'Duration (ms)', 'Post ID', 'Product ID', 'Date',
		) );

		// Write data rows.
		foreach ( $results as $row ) {
			fputcsv( $output, array(
				$row['id'],
				$row['user_id'],
				$row['endpoint'],
				$row['category'],
				$row['model'] ?? '',
				$row['credits_used'],
				$row['response_status'],
				$row['duration_ms'],
				$row['post_id'] ?? '',
				$row['product_id'] ?? '',
				$row['created_at'],
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle CSV export request via admin action.
	 */
	public static function handle_csv_export(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) ) {
			return;
		}

		if ( 'fotohub-ai-analytics' !== $_GET['page'] || 'export_csv' !== $_GET['action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'fotohub-ai' ) );
		}

		check_admin_referer( 'fotohub_export_csv' );

		self::export_csv();
	}

	/**
	 * Get cached credit balance from API.
	 *
	 * @return array|WP_Error Balance data or WP_Error.
	 */
	public static function get_cached_balance(): array|WP_Error {
		$cached = get_transient( self::BALANCE_TRANSIENT );
		if ( false !== $cached ) {
			return $cached;
		}

		$api      = new Fotohub_API();
		$response = $api->get_balance();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		set_transient( self::BALANCE_TRANSIENT, $response, self::BALANCE_TTL );

		return $response;
	}

	/**
	 * Get recent transactions from the billing API.
	 *
	 * @param int $page      Page number.
	 * @param int $page_size Number of transactions per page.
	 * @return array|WP_Error Transactions array or WP_Error.
	 */
	private static function get_recent_transactions( int $page = 1, int $page_size = 50 ): array|WP_Error {
		$api = new Fotohub_API();

		return $api->get_transactions( $page, $page_size );
	}

	/**
	 * Categorize an API endpoint into a usage category.
	 *
	 * @param string $endpoint The API endpoint path.
	 * @return string Category name (image, video, music, chat, other).
	 */
	private static function categorize_endpoint( string $endpoint ): string {
		if ( str_contains( $endpoint, '/generate/image' ) || str_contains( $endpoint, '/remove-background' ) || str_contains( $endpoint, '/upscale' ) ) {
			return 'image';
		}

		if ( str_contains( $endpoint, '/generate/video' ) || str_contains( $endpoint, '/video' ) ) {
			return 'video';
		}

		if ( str_contains( $endpoint, '/generate/music' ) || str_contains( $endpoint, '/audio' ) ) {
			return 'music';
		}

		if ( str_contains( $endpoint, '/chat' ) || str_contains( $endpoint, '/analyze' ) || str_contains( $endpoint, '/enhance-prompt' ) ) {
			return 'chat';
		}

		return 'other';
	}

	/**
	 * Sanitize request data before storing in logs.
	 * Removes sensitive data and large payloads.
	 *
	 * @param array $request The original request body.
	 * @return array Sanitized request data safe for logging.
	 */
	private static function sanitize_request_for_log( array $request ): array {
		// Remove internal tracking fields.
		unset( $request['_post_id'], $request['_product_id'] );

		// Truncate large content fields.
		if ( isset( $request['prompt'] ) && mb_strlen( $request['prompt'] ) > 500 ) {
			$request['prompt'] = mb_substr( $request['prompt'], 0, 500 ) . '...';
		}

		if ( isset( $request['messages'] ) ) {
			$request['messages'] = '[' . count( $request['messages'] ) . ' messages]';
		}

		return $request;
	}
}
