<?php
/**
 * FOTOhub Job Scheduler.
 *
 * WP-Cron based scheduling for bulk generation, retries, and queue processing.
 *
 * @package FotohubAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scheduler class for background job processing.
 */
class Fotohub_Scheduler {

	/**
	 * Database table name (without prefix).
	 */
	const TABLE_NAME = 'fotohub_jobs';

	/**
	 * Valid job types.
	 */
	const JOB_TYPES = array(
		'generate_image',
		'generate_video',
		'remove_background',
		'bulk_product_photos',
		'bulk_alt_text',
	);

	/**
	 * Valid job statuses.
	 */
	const STATUSES = array( 'pending', 'running', 'completed', 'failed' );

	/**
	 * Default max retry attempts.
	 */
	const DEFAULT_MAX_ATTEMPTS = 3;

	/**
	 * Initialize scheduler hooks.
	 */
	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_intervals' ) );
		add_action( 'fotohub_process_queue', array( __CLASS__, 'process_queue' ) );
		add_action( 'fotohub_nightly_batch', array( __CLASS__, 'run_nightly_batch' ) );
		add_action( 'fotohub_retry_failed', array( __CLASS__, 'retry_failed' ) );

		// Schedule cron events if not already scheduled.
		if ( ! wp_next_scheduled( 'fotohub_process_queue' ) ) {
			wp_schedule_event( time(), 'fotohub_every_5_minutes', 'fotohub_process_queue' );
		}

		if ( ! wp_next_scheduled( 'fotohub_nightly_batch' ) ) {
			// Schedule nightly at 2:00 AM local time.
			$next_2am = self::get_next_2am_timestamp();
			wp_schedule_event( $next_2am, 'daily', 'fotohub_nightly_batch' );
		}

		if ( ! wp_next_scheduled( 'fotohub_retry_failed' ) ) {
			wp_schedule_event( time(), 'fotohub_every_5_minutes', 'fotohub_retry_failed' );
		}
	}

	/**
	 * Register custom cron intervals.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public static function register_cron_intervals( array $schedules ): array {
		$schedules['fotohub_every_5_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes (FOTOhub)', 'fotohub-ai' ),
		);

		$schedules['fotohub_nightly'] = array(
			'interval' => DAY_IN_SECONDS,
			'display'  => __( 'Once Daily at 2 AM (FOTOhub)', 'fotohub-ai' ),
		);

		return $schedules;
	}

	/**
	 * Get the next 2 AM timestamp based on site timezone.
	 *
	 * @return int Unix timestamp for next 2 AM.
	 */
	private static function get_next_2am_timestamp(): int {
		$timezone_string = get_option( 'timezone_string', 'UTC' );
		if ( empty( $timezone_string ) ) {
			$gmt_offset      = get_option( 'gmt_offset', 0 );
			$timezone_string = timezone_name_from_abbr( '', $gmt_offset * HOUR_IN_SECONDS, 0 );
			if ( false === $timezone_string ) {
				$timezone_string = 'UTC';
			}
		}

		try {
			$tz  = new DateTimeZone( $timezone_string );
			$now = new DateTime( 'now', $tz );
			$target = new DateTime( 'today 02:00', $tz );

			if ( $now > $target ) {
				$target->modify( '+1 day' );
			}

			return $target->getTimestamp();
		} catch ( Exception $e ) {
			// Fallback: schedule 2 hours from now.
			return time() + ( 2 * HOUR_IN_SECONDS );
		}
	}

	/**
	 * Create the jobs database table.
	 */
	public static function install_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_type varchar(50) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			payload longtext NOT NULL,
			result longtext DEFAULT NULL,
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			max_attempts smallint(5) unsigned NOT NULL DEFAULT 3,
			scheduled_at datetime DEFAULT NULL,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY job_type (job_type),
			KEY scheduled_at (scheduled_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Schedule a new job.
	 *
	 * @param string      $type         Job type (one of JOB_TYPES).
	 * @param array       $payload      Job payload data.
	 * @param string|null $scheduled_at Optional datetime string for delayed execution.
	 * @return int Job ID.
	 */
	public static function schedule_job( string $type, array $payload, ?string $scheduled_at = null ): int {
		global $wpdb;

		if ( ! in_array( $type, self::JOB_TYPES, true ) ) {
			return 0;
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$data = array(
			'job_type'     => $type,
			'status'       => 'pending',
			'payload'      => wp_json_encode( $payload ),
			'attempts'     => 0,
			'max_attempts' => self::DEFAULT_MAX_ATTEMPTS,
			'created_at'   => current_time( 'mysql', true ),
		);

		$formats = array( '%s', '%s', '%s', '%d', '%d', '%s' );

		if ( null !== $scheduled_at ) {
			$data['scheduled_at'] = $scheduled_at;
			$formats[]            = '%s';
		}

		$wpdb->insert( $table_name, $data, $formats );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Process pending jobs from the queue.
	 *
	 * @param int $batch_size Number of jobs to process per run.
	 */
	public static function process_queue( int $batch_size = 5 ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$now        = current_time( 'mysql', true );

		// Get pending jobs that are either not scheduled or scheduled for now/past.
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				WHERE status = 'pending'
				AND (scheduled_at IS NULL OR scheduled_at <= %s)
				ORDER BY created_at ASC
				LIMIT %d",
				$now,
				$batch_size
			)
		);

		if ( empty( $jobs ) ) {
			return;
		}

		foreach ( $jobs as $job ) {
			self::execute_job( $job );
		}
	}

	/**
	 * Execute a single job.
	 *
	 * @param object $job Job row object from database.
	 */
	private static function execute_job( object $job ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Mark as running.
		$wpdb->update(
			$table_name,
			array(
				'status'     => 'running',
				'started_at' => current_time( 'mysql', true ),
				'attempts'   => (int) $job->attempts + 1,
			),
			array( 'id' => $job->id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		$payload = json_decode( $job->payload, true );
		$result  = null;

		try {
			$api = new Fotohub_API();

			switch ( $job->job_type ) {
				case 'generate_image':
					$result = $api->generate_image(
						$payload['prompt'] ?? '',
						$payload['options'] ?? array()
					);
					break;

				case 'generate_video':
					$result = $api->generate_video(
						$payload['prompt'] ?? '',
						$payload['options'] ?? array()
					);
					break;

				case 'remove_background':
					$result = $api->remove_background( $payload['image_url'] ?? '' );
					break;

				case 'bulk_product_photos':
					$result = self::process_bulk_product_photos( $payload );
					break;

				case 'bulk_alt_text':
					$result = self::process_bulk_alt_text( $payload );
					break;

				default:
					$result = new WP_Error( 'unknown_job_type', __( 'Unknown job type.', 'fotohub-ai' ) );
					break;
			}

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Mark as completed.
			$wpdb->update(
				$table_name,
				array(
					'status'       => 'completed',
					'result'       => wp_json_encode( $result ),
					'completed_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $job->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Fire completion action.
			do_action( 'fotohub_job_completed', (int) $job->id, $result );

			// Send notification.
			self::notify_completion( (int) $job->id );

		} catch ( Exception $e ) {
			$attempts = (int) $job->attempts + 1;
			$status   = $attempts >= (int) $job->max_attempts ? 'failed' : 'pending';

			$wpdb->update(
				$table_name,
				array(
					'status' => $status,
					'result' => wp_json_encode( array( 'error' => $e->getMessage() ) ),
				),
				array( 'id' => $job->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Process bulk product photo generation.
	 *
	 * @param array $payload Job payload with product_ids and options.
	 * @return array Results array.
	 */
	private static function process_bulk_product_photos( array $payload ): array {
		$product_ids = $payload['product_ids'] ?? array();
		$options     = $payload['options'] ?? array();
		$results     = array();

		$api = new Fotohub_API();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$results[ $product_id ] = array( 'error' => 'Product not found' );
				continue;
			}

			$prompt = sprintf(
				'Professional product photo of %s. %s',
				$product->get_name(),
				$product->get_short_description()
			);

			$result = $api->generate_image( $prompt, $options );

			if ( is_wp_error( $result ) ) {
				$results[ $product_id ] = array( 'error' => $result->get_error_message() );
			} else {
				$results[ $product_id ] = $result;
			}
		}

		return $results;
	}

	/**
	 * Process bulk alt text generation.
	 *
	 * @param array $payload Job payload with attachment_ids.
	 * @return array Results array.
	 */
	private static function process_bulk_alt_text( array $payload ): array {
		$attachment_ids = $payload['attachment_ids'] ?? array();
		$results        = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				$results[ $attachment_id ] = array( 'error' => 'Attachment not found' );
				continue;
			}

			// Use the FOTOhub API to generate alt text.
			$api    = new Fotohub_API();
			$result = $api->generate_alt_text( $image_url );

			if ( is_wp_error( $result ) ) {
				$results[ $attachment_id ] = array( 'error' => $result->get_error_message() );
			} else {
				$alt_text = $result['alt_text'] ?? '';
				if ( ! empty( $alt_text ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
				}
				$results[ $attachment_id ] = $result;
			}
		}

		return $results;
	}

	/**
	 * Retry failed jobs that haven't exceeded max attempts.
	 */
	public static function retry_failed(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$wpdb->query(
			"UPDATE {$table_name}
			SET status = 'pending'
			WHERE status = 'failed'
			AND attempts < max_attempts"
		);
	}

	/**
	 * Run the nightly batch process.
	 * Processes larger batches during off-peak hours.
	 */
	public static function run_nightly_batch(): void {
		// Process up to 50 jobs during nightly run.
		self::process_queue( 50 );
	}

	/**
	 * Get jobs with optional filtering.
	 *
	 * @param string $status   Filter by status ('all' for no filter).
	 * @param int    $page     Page number (1-based).
	 * @param int    $per_page Items per page.
	 * @return array Array with 'jobs' and 'total' keys.
	 */
	public static function get_jobs( string $status = 'all', int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$offset     = ( $page - 1 ) * $per_page;

		$where = '';
		if ( 'all' !== $status && in_array( $status, self::STATUSES, true ) ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} {$where}" );

		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		return array(
			'jobs'  => $jobs ?? array(),
			'total' => $total,
		);
	}

	/**
	 * Cancel a pending or running job.
	 *
	 * @param int $job_id Job ID to cancel.
	 * @return bool Whether the job was cancelled successfully.
	 */
	public static function cancel_job( int $job_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$updated = $wpdb->update(
			$table_name,
			array(
				'status'       => 'failed',
				'result'       => wp_json_encode( array( 'error' => 'Cancelled by user' ) ),
				'completed_at' => current_time( 'mysql', true ),
			),
			array(
				'id' => $job_id,
			),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated && $updated > 0;
	}

	/**
	 * Send email notification when a job completes.
	 *
	 * @param int $job_id Completed job ID.
	 */
	public static function notify_completion( int $job_id ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$job = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $job_id )
		);

		if ( ! $job || 'completed' !== $job->status ) {
			return;
		}

		// Only send notifications if enabled.
		if ( 'yes' !== get_option( 'fotohub_ai_notify_completion', 'yes' ) ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: site name, 2: job type */
			__( '[%1$s] FOTOhub job completed: %2$s', 'fotohub-ai' ),
			$site_name,
			ucfirst( str_replace( '_', ' ', $job->job_type ) )
		);

		$result  = json_decode( $job->result, true );
		$message = sprintf(
			/* translators: 1: job type, 2: job ID, 3: completion time */
			__( "Your FOTOhub %1\$s job (ID: %2\$d) has completed successfully.\n\nCompleted at: %3\$s\nAttempts: %4\$d\n\nView details in your WordPress admin:\n%5\$s", 'fotohub-ai' ),
			ucfirst( str_replace( '_', ' ', $job->job_type ) ),
			$job->id,
			$job->completed_at,
			$job->attempts,
			admin_url( 'admin.php?page=fotohub-ai-scheduler' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Get a single job by ID.
	 *
	 * @param int $job_id Job ID.
	 * @return object|null Job object or null if not found.
	 */
	public static function get_job( int $job_id ): ?object {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$job = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $job_id )
		);

		return $job ?: null;
	}

	/**
	 * Get job count by status.
	 *
	 * @return array Associative array of status => count.
	 */
	public static function get_status_counts(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status"
		);

		$counts = array(
			'pending'   => 0,
			'running'   => 0,
			'completed' => 0,
			'failed'    => 0,
		);

		if ( $results ) {
			foreach ( $results as $row ) {
				$counts[ $row->status ] = (int) $row->count;
			}
		}

		$counts['all'] = array_sum( $counts );

		return $counts;
	}

	/**
	 * Clean up old completed jobs.
	 *
	 * @param int $days_old Delete completed jobs older than this many days.
	 * @return int Number of rows deleted.
	 */
	public static function cleanup_old_jobs( int $days_old = 30 ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE status = 'completed' AND completed_at < %s",
				$cutoff
			)
		);
	}

	/**
	 * Unschedule all FOTOhub cron events (on plugin deactivation).
	 */
	public static function unschedule_all(): void {
		wp_clear_scheduled_hook( 'fotohub_process_queue' );
		wp_clear_scheduled_hook( 'fotohub_nightly_batch' );
		wp_clear_scheduled_hook( 'fotohub_retry_failed' );
	}

	/**
	 * Drop the jobs table (on plugin uninstall).
	 */
	public static function uninstall_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
