<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wp_email_connector_settings');

global $wpdb;
$table_name = $wpdb->prefix . 'wp_email_connector_logs';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");
