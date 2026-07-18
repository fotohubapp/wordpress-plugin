<?php
/**
 * FOTOhub Job Scheduler admin page template.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_tab = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
$page_num    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page    = 20;

$counts = Fotohub_Scheduler::get_status_counts();
$data   = Fotohub_Scheduler::get_jobs( $current_tab, $page_num, $per_page );
$jobs   = $data['jobs'];
$total  = $data['total'];

$total_pages = ceil( $total / $per_page );

$tabs = array(
	'all'       => sprintf(
		/* translators: %d: number of jobs */
		__( 'All (%d)', 'fotohub-ai' ),
		$counts['all']
	),
	'pending'   => sprintf(
		/* translators: %d: number of pending jobs */
		__( 'Pending (%d)', 'fotohub-ai' ),
		$counts['pending']
	),
	'running'   => sprintf(
		/* translators: %d: number of running jobs */
		__( 'Running (%d)', 'fotohub-ai' ),
		$counts['running']
	),
	'completed' => sprintf(
		/* translators: %d: number of completed jobs */
		__( 'Completed (%d)', 'fotohub-ai' ),
		$counts['completed']
	),
	'failed'    => sprintf(
		/* translators: %d: number of failed jobs */
		__( 'Failed (%d)', 'fotohub-ai' ),
		$counts['failed']
	),
);

$job_type_labels = array(
	'generate_image'      => __( 'Generate Image', 'fotohub-ai' ),
	'generate_video'      => __( 'Generate Video', 'fotohub-ai' ),
	'remove_background'   => __( 'Remove Background', 'fotohub-ai' ),
	'bulk_product_photos' => __( 'Bulk Product Photos', 'fotohub-ai' ),
	'bulk_alt_text'       => __( 'Bulk Alt Text', 'fotohub-ai' ),
);

$status_classes = array(
	'pending'   => 'fotohub-badge-blue',
	'running'   => 'fotohub-badge-orange',
	'completed' => 'fotohub-badge-green',
	'failed'    => 'fotohub-badge-red',
);
?>
<div class="wrap fotohub-scheduler-wrap">
	<h1>
		<span class="dashicons dashicons-clock" style="font-size: 28px; margin-right: 8px; vertical-align: middle;"></span>
		<?php esc_html_e( 'FOTOhub Job Scheduler', 'fotohub-ai' ); ?>
		<button type="button" class="page-title-action" id="fotohub-schedule-new-job-btn">
			<?php esc_html_e( 'Schedule New Job', 'fotohub-ai' ); ?>
		</button>
	</h1>

	<!-- Auto-refresh toggle -->
	<div class="fotohub-scheduler-toolbar">
		<label class="fotohub-auto-refresh-toggle">
			<input type="checkbox" id="fotohub-auto-refresh" <?php checked( $counts['running'] > 0 ); ?>>
			<?php esc_html_e( 'Auto-refresh (every 30s)', 'fotohub-ai' ); ?>
		</label>
		<span class="fotohub-last-refresh" id="fotohub-last-refresh"></span>
	</div>

	<!-- New Job Form (hidden by default) -->
	<div class="fotohub-new-job-form" id="fotohub-new-job-form" style="display:none;">
		<div class="card">
			<h2><?php esc_html_e( 'Schedule New Job', 'fotohub-ai' ); ?></h2>

			<div class="fotohub-form-group">
				<label for="fotohub-new-job-type"><?php esc_html_e( 'Job Type', 'fotohub-ai' ); ?></label>
				<select id="fotohub-new-job-type">
					<option value=""><?php esc_html_e( '-- Select Type --', 'fotohub-ai' ); ?></option>
					<?php foreach ( $job_type_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Dynamic payload fields -->
			<div class="fotohub-job-payload-fields" id="fotohub-job-payload-fields">
				<!-- generate_image fields -->
				<div class="fotohub-payload-section" data-job-type="generate_image" style="display:none;">
					<div class="fotohub-form-group">
						<label for="fotohub-job-prompt"><?php esc_html_e( 'Prompt', 'fotohub-ai' ); ?></label>
						<textarea id="fotohub-job-prompt" rows="3" class="large-text"
								  placeholder="<?php esc_attr_e( 'Describe the image...', 'fotohub-ai' ); ?>"></textarea>
					</div>
					<div class="fotohub-form-group">
						<label for="fotohub-job-model"><?php esc_html_e( 'Model', 'fotohub-ai' ); ?></label>
						<select id="fotohub-job-model">
							<option value="seedream-5-0-260128"><?php esc_html_e( 'Seedream 5.0', 'fotohub-ai' ); ?></option>
							<option value="flux-1-schnell"><?php esc_html_e( 'Flux 1 Schnell', 'fotohub-ai' ); ?></option>
							<option value="flux-1-dev"><?php esc_html_e( 'Flux 1 Dev', 'fotohub-ai' ); ?></option>
							<option value="stable-diffusion-xl"><?php esc_html_e( 'Stable Diffusion XL', 'fotohub-ai' ); ?></option>
						</select>
					</div>
				</div>

				<!-- generate_video fields -->
				<div class="fotohub-payload-section" data-job-type="generate_video" style="display:none;">
					<div class="fotohub-form-group">
						<label for="fotohub-job-video-prompt"><?php esc_html_e( 'Prompt', 'fotohub-ai' ); ?></label>
						<textarea id="fotohub-job-video-prompt" rows="3" class="large-text"
								  placeholder="<?php esc_attr_e( 'Describe the video...', 'fotohub-ai' ); ?>"></textarea>
					</div>
					<div class="fotohub-form-group">
						<label for="fotohub-job-video-model"><?php esc_html_e( 'Model', 'fotohub-ai' ); ?></label>
						<select id="fotohub-job-video-model">
							<option value="veo-3"><?php esc_html_e( 'Veo 3', 'fotohub-ai' ); ?></option>
							<option value="veo-2"><?php esc_html_e( 'Veo 2', 'fotohub-ai' ); ?></option>
							<option value="kling"><?php esc_html_e( 'Kling', 'fotohub-ai' ); ?></option>
							<option value="hailuo"><?php esc_html_e( 'Hailuo', 'fotohub-ai' ); ?></option>
						</select>
					</div>
				</div>

				<!-- remove_background fields -->
				<div class="fotohub-payload-section" data-job-type="remove_background" style="display:none;">
					<div class="fotohub-form-group">
						<label for="fotohub-job-image-url"><?php esc_html_e( 'Image URL', 'fotohub-ai' ); ?></label>
						<input type="url" id="fotohub-job-image-url" class="large-text"
							   placeholder="<?php esc_attr_e( 'https://...', 'fotohub-ai' ); ?>">
						<button type="button" class="button" id="fotohub-job-select-image">
							<?php esc_html_e( 'Select from Media Library', 'fotohub-ai' ); ?>
						</button>
					</div>
				</div>

				<!-- bulk_product_photos fields -->
				<div class="fotohub-payload-section" data-job-type="bulk_product_photos" style="display:none;">
					<div class="fotohub-form-group">
						<label for="fotohub-job-product-ids"><?php esc_html_e( 'Product IDs', 'fotohub-ai' ); ?></label>
						<input type="text" id="fotohub-job-product-ids" class="large-text"
							   placeholder="<?php esc_attr_e( 'Comma-separated product IDs (e.g., 101, 102, 103)', 'fotohub-ai' ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty to process all products without images.', 'fotohub-ai' ); ?></p>
					</div>
				</div>

				<!-- bulk_alt_text fields -->
				<div class="fotohub-payload-section" data-job-type="bulk_alt_text" style="display:none;">
					<div class="fotohub-form-group">
						<label for="fotohub-job-attachment-ids"><?php esc_html_e( 'Attachment IDs', 'fotohub-ai' ); ?></label>
						<input type="text" id="fotohub-job-attachment-ids" class="large-text"
							   placeholder="<?php esc_attr_e( 'Comma-separated attachment IDs, or leave empty for all images without alt text', 'fotohub-ai' ); ?>">
					</div>
				</div>
			</div>

			<!-- Schedule Time -->
			<div class="fotohub-form-group">
				<label for="fotohub-job-schedule"><?php esc_html_e( 'Schedule', 'fotohub-ai' ); ?></label>
				<select id="fotohub-job-schedule-type">
					<option value="now"><?php esc_html_e( 'Run Now', 'fotohub-ai' ); ?></option>
					<option value="scheduled"><?php esc_html_e( 'Schedule for Later', 'fotohub-ai' ); ?></option>
				</select>
				<input type="datetime-local" id="fotohub-job-schedule-time" style="display:none; margin-top: 8px;">
			</div>

			<div class="fotohub-form-actions">
				<button type="button" class="button button-primary" id="fotohub-submit-new-job">
					<?php esc_html_e( 'Schedule Job', 'fotohub-ai' ); ?>
				</button>
				<button type="button" class="button" id="fotohub-cancel-new-job">
					<?php esc_html_e( 'Cancel', 'fotohub-ai' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Tab Bar -->
	<ul class="subsubsub fotohub-scheduler-tabs">
		<?php
		$tab_links = array();
		foreach ( $tabs as $tab_key => $tab_label ) :
			$url       = add_query_arg(
				array(
					'page'   => 'fotohub-ai-scheduler',
					'status' => $tab_key,
				),
				admin_url( 'admin.php' )
			);
			$class     = ( $current_tab === $tab_key ) ? 'current' : '';
			$tab_links[] = sprintf(
				'<li><a href="%s" class="%s">%s</a></li>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $tab_label )
			);
		endforeach;
		echo implode( ' | ', $tab_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</ul>

	<br class="clear">

	<!-- Jobs Table -->
	<table class="widefat striped fotohub-jobs-table" id="fotohub-jobs-table">
		<thead>
			<tr>
				<th class="column-id"><?php esc_html_e( 'ID', 'fotohub-ai' ); ?></th>
				<th class="column-type"><?php esc_html_e( 'Type', 'fotohub-ai' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Status', 'fotohub-ai' ); ?></th>
				<th class="column-scheduled"><?php esc_html_e( 'Scheduled', 'fotohub-ai' ); ?></th>
				<th class="column-started"><?php esc_html_e( 'Started', 'fotohub-ai' ); ?></th>
				<th class="column-completed"><?php esc_html_e( 'Completed', 'fotohub-ai' ); ?></th>
				<th class="column-attempts"><?php esc_html_e( 'Attempts', 'fotohub-ai' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'fotohub-ai' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $jobs ) ) : ?>
				<tr>
					<td colspan="8" class="fotohub-no-jobs">
						<?php esc_html_e( 'No jobs found.', 'fotohub-ai' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $jobs as $job ) : ?>
					<tr data-job-id="<?php echo esc_attr( $job->id ); ?>">
						<td class="column-id">
							<strong>#<?php echo esc_html( $job->id ); ?></strong>
						</td>
						<td class="column-type">
							<?php echo esc_html( $job_type_labels[ $job->job_type ] ?? $job->job_type ); ?>
						</td>
						<td class="column-status">
							<span class="fotohub-badge <?php echo esc_attr( $status_classes[ $job->status ] ?? '' ); ?>">
								<?php echo esc_html( ucfirst( $job->status ) ); ?>
							</span>
						</td>
						<td class="column-scheduled">
							<?php
							if ( $job->scheduled_at ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job->scheduled_at ) ) );
							} else {
								echo '<span class="description">' . esc_html__( 'Immediate', 'fotohub-ai' ) . '</span>';
							}
							?>
						</td>
						<td class="column-started">
							<?php
							if ( $job->started_at ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job->started_at ) ) );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td class="column-completed">
							<?php
							if ( $job->completed_at ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job->completed_at ) ) );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td class="column-attempts">
							<?php
							echo esc_html( $job->attempts . '/' . $job->max_attempts );
							?>
						</td>
						<td class="column-actions">
							<?php if ( in_array( $job->status, array( 'pending', 'running' ), true ) ) : ?>
								<button type="button" class="button button-small fotohub-job-cancel" data-job-id="<?php echo esc_attr( $job->id ); ?>">
									<?php esc_html_e( 'Cancel', 'fotohub-ai' ); ?>
								</button>
							<?php endif; ?>

							<?php if ( 'failed' === $job->status && (int) $job->attempts < (int) $job->max_attempts ) : ?>
								<button type="button" class="button button-small fotohub-job-retry" data-job-id="<?php echo esc_attr( $job->id ); ?>">
									<?php esc_html_e( 'Retry', 'fotohub-ai' ); ?>
								</button>
							<?php endif; ?>

							<button type="button" class="button button-small fotohub-job-view" data-job-id="<?php echo esc_attr( $job->id ); ?>">
								<?php esc_html_e( 'View', 'fotohub-ai' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: number of items */
						esc_html( _n( '%s item', '%s items', $total, 'fotohub-ai' ) ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</span>
				<span class="pagination-links">
					<?php
					$base_url = add_query_arg(
						array(
							'page'   => 'fotohub-ai-scheduler',
							'status' => $current_tab,
						),
						admin_url( 'admin.php' )
					);

					if ( $page_num > 1 ) :
						?>
						<a class="first-page button" href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">
							<span aria-hidden="true">&laquo;</span>
						</a>
						<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page_num - 1, $base_url ) ); ?>">
							<span aria-hidden="true">&lsaquo;</span>
						</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
					<?php endif; ?>

					<span class="paging-input">
						<span class="tablenav-paging-text">
							<?php echo esc_html( $page_num ); ?>
							<?php esc_html_e( 'of', 'fotohub-ai' ); ?>
							<span class="total-pages"><?php echo esc_html( $total_pages ); ?></span>
						</span>
					</span>

					<?php if ( $page_num < $total_pages ) : ?>
						<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page_num + 1, $base_url ) ); ?>">
							<span aria-hidden="true">&rsaquo;</span>
						</a>
						<a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ); ?>">
							<span aria-hidden="true">&raquo;</span>
						</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
					<?php endif; ?>
				</span>
			</div>
		</div>
	<?php endif; ?>

	<!-- Job Detail Modal -->
	<div id="fotohub-job-detail-modal" class="fotohub-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="fotohub-job-detail-title">
		<div class="fotohub-modal-backdrop"></div>
		<div class="fotohub-modal-content">
			<div class="fotohub-modal-header">
				<h2 id="fotohub-job-detail-title"><?php esc_html_e( 'Job Details', 'fotohub-ai' ); ?></h2>
				<button type="button" class="fotohub-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fotohub-ai' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<div class="fotohub-modal-body">
				<table class="widefat">
					<tbody id="fotohub-job-detail-body">
					</tbody>
				</table>
				<h3><?php esc_html_e( 'Payload', 'fotohub-ai' ); ?></h3>
				<pre id="fotohub-job-detail-payload" class="fotohub-code-block"></pre>
				<h3><?php esc_html_e( 'Result', 'fotohub-ai' ); ?></h3>
				<pre id="fotohub-job-detail-result" class="fotohub-code-block"></pre>
			</div>
			<div class="fotohub-modal-footer">
				<button type="button" class="button fotohub-modal-close">
					<?php esc_html_e( 'Close', 'fotohub-ai' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
