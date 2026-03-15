<?php
/**
 * Plugin Name: WordPress Email Connector
 * Description: Konfigurierbarer E-Mail-Versand fuer WordPress mit SMTP-Option und Versand-Logging.
 * Version: __BUILD_VERSION__
 * Author: Henrik Hansen
 * Text Domain: wordpress-email
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_EMAIL_CONNECTOR_VERSION', '__BUILD_VERSION__');
define('WP_EMAIL_CONNECTOR_FILE', __FILE__);
define('WP_EMAIL_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('WP_EMAIL_CONNECTOR_URL', plugin_dir_url(__FILE__));

require_once WP_EMAIL_CONNECTOR_PATH . 'includes/class-wp-email-connector.php';

register_activation_hook(__FILE__, array('WP_Email_Connector', 'activate'));

function wp_email_connector_init()
{
    WP_Email_Connector::instance();
}

add_action('plugins_loaded', 'wp_email_connector_init');
