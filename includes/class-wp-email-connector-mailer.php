<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Email_Connector_Mailer
{
    /**
     * @var WP_Email_Connector_Settings
     */
    private $settings;

    public function __construct(WP_Email_Connector_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function init()
    {
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));
        add_filter('wp_mail_from', array($this, 'filter_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'filter_mail_from_name'));
    }

    /**
     * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public function configure_phpmailer($phpmailer)
    {
        $transport = (string) $this->settings->get('transport', 'phpmailer');

        if ($transport !== 'external') {
            return;
        }

        $smtp_host = (string) $this->settings->get('smtp_host', '');
        $smtp_port = (int) $this->settings->get('smtp_port', 587);
        $smtp_auth = (int) $this->settings->get('smtp_auth', 1) === 1;
        $smtp_username = (string) $this->settings->get('smtp_username', '');
        $smtp_password = (string) $this->settings->get('smtp_password', '');
        $smtp_encryption = (string) $this->settings->get('smtp_encryption', 'tls');

        if ($smtp_host === '') {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp_host;
        $phpmailer->Port = $smtp_port;
        $phpmailer->SMTPAuth = $smtp_auth;
        $phpmailer->Username = $smtp_username;
        $phpmailer->Password = $smtp_password;

        if ($smtp_encryption === 'ssl' || $smtp_encryption === 'tls') {
            $phpmailer->SMTPSecure = $smtp_encryption;
        } else {
            $phpmailer->SMTPSecure = '';
        }
    }

    /**
     * @param string $from_email
     * @return string
     */
    public function filter_mail_from($from_email)
    {
        $custom = (string) $this->settings->get('from_email', '');

        if ($custom !== '' && is_email($custom)) {
            return $custom;
        }

        return $from_email;
    }

    /**
     * @param string $from_name
     * @return string
     */
    public function filter_mail_from_name($from_name)
    {
        $custom = (string) $this->settings->get('from_name', '');

        if ($custom !== '') {
            return $custom;
        }

        return $from_name;
    }
}
