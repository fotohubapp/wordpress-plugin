<?php
/**
 * Uninstall FOTOhub AI plugin.
 *
 * Removes all plugin data from the database when the plugin is deleted.
 *
 * @package FotohubAI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'fotohub_ai_api_key' );
delete_option( 'fotohub_ai_default_model' );
delete_option( 'fotohub_ai_default_width' );
delete_option( 'fotohub_ai_default_height' );

// Remove transients.
delete_transient( 'fotohub_ai_models_cache' );
delete_transient( 'fotohub_ai_balance_cache' );

// Remove any scheduled hooks.
wp_clear_scheduled_hook( 'fotohub_ai_clear_cache' );
