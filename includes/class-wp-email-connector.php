<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once WP_EMAIL_CONNECTOR_PATH . 'includes/class-wp-email-connector-settings.php';
require_once WP_EMAIL_CONNECTOR_PATH . 'includes/class-wp-email-connector-mailer.php';
require_once WP_EMAIL_CONNECTOR_PATH . 'includes/class-wp-email-connector-logger.php';

class WP_Email_Connector
{
    /**
     * @var WP_Email_Connector|null
     */
    private static $instance = null;

    /**
     * @var WP_Email_Connector_Settings
     */
    private $settings;

    /**
     * @var WP_Email_Connector_Mailer
     */
    private $mailer;

    /**
     * @var WP_Email_Connector_Logger
     */
    private $logger;

    /**
     * @return WP_Email_Connector
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate()
    {
        WP_Email_Connector_Logger::create_table();
    }

    private function __construct()
    {
        $this->settings = new WP_Email_Connector_Settings();
        $this->logger = new WP_Email_Connector_Logger($this->settings);
        $this->mailer = new WP_Email_Connector_Mailer($this->settings);

        $this->settings->init($this->logger);
        $this->mailer->init();
        $this->logger->init();
    }
}
