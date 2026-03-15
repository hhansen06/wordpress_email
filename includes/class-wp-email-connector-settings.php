<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Email_Connector_Settings
{
    const OPTION_NAME = 'wp_email_connector_settings';

    /**
     * @var array<string, mixed>
     */
    private $settings = array();

    /**
     * @var WP_Email_Connector_Logger|null
     */
    private $logger;

    public function __construct()
    {
        $this->settings = $this->get_settings();
    }

    public function init(WP_Email_Connector_Logger $logger)
    {
        $this->logger = $logger;

        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_dashboard_setup', array($this, 'register_dashboard_widget'));
    }

    public function register_dashboard_widget()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'wp_email_connector_dashboard_logs',
            __('Mail Logs (WP Email)', 'wordpress-email'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get_settings()
    {
        $saved = get_option(self::OPTION_NAME, array());

        $defaults = array(
            'transport' => 'phpmailer',
            'from_email' => '',
            'from_name' => '',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_auth' => 1,
            'smtp_username' => '',
            'smtp_password' => '',
            'log_enabled' => 1,
        );

        return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
    }

    /**
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (!isset($this->settings[$key])) {
            return $default;
        }

        return $this->settings[$key];
    }

    public function register_admin_menu()
    {
        add_menu_page(
            __('WP Email', 'wordpress-email'),
            __('WP Email', 'wordpress-email'),
            'manage_options',
            'wp-email-connector-settings',
            array($this, 'render_settings_page'),
            'dashicons-email',
            80
        );

        add_submenu_page(
            'wp-email-connector-settings',
            __('Einstellungen', 'wordpress-email'),
            __('Einstellungen', 'wordpress-email'),
            'manage_options',
            'wp-email-connector-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'wp-email-connector-settings',
            __('Mail Logs', 'wordpress-email'),
            __('Mail Logs', 'wordpress-email'),
            'manage_options',
            'wp-email-connector-logs',
            array($this, 'render_logs_page')
        );
    }

    public function register_settings()
    {
        register_setting(
            'wp_email_connector_settings_group',
            self::OPTION_NAME,
            array($this, 'sanitize_settings')
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sanitize_settings($input)
    {
        $clean = array();

        $clean['transport'] = isset($input['transport']) && $input['transport'] === 'external' ? 'external' : 'phpmailer';
        $clean['from_email'] = isset($input['from_email']) ? sanitize_email($input['from_email']) : '';
        $clean['from_name'] = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : '';
        $clean['smtp_host'] = isset($input['smtp_host']) ? sanitize_text_field($input['smtp_host']) : '';
        $clean['smtp_port'] = isset($input['smtp_port']) ? absint($input['smtp_port']) : 587;
        $clean['smtp_encryption'] = isset($input['smtp_encryption']) ? sanitize_text_field($input['smtp_encryption']) : 'tls';
        $clean['smtp_auth'] = !empty($input['smtp_auth']) ? 1 : 0;
        $clean['smtp_username'] = isset($input['smtp_username']) ? sanitize_text_field($input['smtp_username']) : '';

        // Password is intentionally not sanitized by text filter to preserve valid characters.
        $clean['smtp_password'] = isset($input['smtp_password']) ? (string) $input['smtp_password'] : '';
        $clean['log_enabled'] = !empty($input['log_enabled']) ? 1 : 0;

        if (!in_array($clean['smtp_encryption'], array('none', 'ssl', 'tls'), true)) {
            $clean['smtp_encryption'] = 'tls';
        }

        if ($clean['smtp_port'] < 1 || $clean['smtp_port'] > 65535) {
            $clean['smtp_port'] = 587;
        }

        $this->settings = wp_parse_args($clean, $this->get_settings());

        return $this->settings;
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WordPress Email Connector', 'wordpress-email'); ?></h1>
            <p><?php esc_html_e('Konfiguriere den Mailversand fuer diese WordPress-Installation.', 'wordpress-email'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('wp_email_connector_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Mailserver', 'wordpress-email'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="<?php echo esc_attr(self::OPTION_NAME); ?>[transport]"
                                            value="phpmailer" <?php checked($settings['transport'], 'phpmailer'); ?> />
                                        <?php esc_html_e('PHP Mailer (WordPress-Standard)', 'wordpress-email'); ?>
                                    </label>
                                    <br />
                                    <label>
                                        <input type="radio" name="<?php echo esc_attr(self::OPTION_NAME); ?>[transport]"
                                            value="external" <?php checked($settings['transport'], 'external'); ?> />
                                        <?php esc_html_e('Externer SMTP-Server', 'wordpress-email'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label
                                    for="from_email"><?php esc_html_e('From E-Mail', 'wordpress-email'); ?></label></th>
                            <td>
                                <input id="from_email" type="email" class="regular-text"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[from_email]"
                                    value="<?php echo esc_attr($settings['from_email']); ?>" />
                                <p class="description">
                                    <?php esc_html_e('Optional: Ueberschreibt die Absenderadresse.', 'wordpress-email'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="from_name"><?php esc_html_e('From Name', 'wordpress-email'); ?></label>
                            </th>
                            <td>
                                <input id="from_name" type="text" class="regular-text"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[from_name]"
                                    value="<?php echo esc_attr($settings['from_name']); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="smtp_host"><?php esc_html_e('SMTP Host', 'wordpress-email'); ?></label>
                            </th>
                            <td>
                                <input id="smtp_host" type="text" class="regular-text"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[smtp_host]"
                                    value="<?php echo esc_attr($settings['smtp_host']); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="smtp_port"><?php esc_html_e('SMTP Port', 'wordpress-email'); ?></label>
                            </th>
                            <td>
                                <input id="smtp_port" type="number" min="1" max="65535"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[smtp_port]"
                                    value="<?php echo esc_attr((string) $settings['smtp_port']); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label
                                    for="smtp_encryption"><?php esc_html_e('Verschluesselung', 'wordpress-email'); ?></label>
                            </th>
                            <td>
                                <select id="smtp_encryption" name="<?php echo esc_attr(self::OPTION_NAME); ?>[smtp_encryption]">
                                    <option value="none" <?php selected($settings['smtp_encryption'], 'none'); ?>>
                                        <?php esc_html_e('Keine', 'wordpress-email'); ?></option>
                                    <option value="ssl" <?php selected($settings['smtp_encryption'], 'ssl'); ?>>SSL</option>
                                    <option value="tls" <?php selected($settings['smtp_encryption'], 'tls'); ?>>TLS</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('SMTP Authentifizierung', 'wordpress-email'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[smtp_auth]"
                                        value="1" <?php checked($settings['smtp_auth'], 1); ?> />
                                    <?php esc_html_e('Benutzername und Passwort verwenden', 'wordpress-email'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label
                                    for="smtp_username"><?php esc_html_e('SMTP Benutzername', 'wordpress-email'); ?></label>
                            </th>
                            <td>
                                <input id="smtp_username" type="text" class="regular-text"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[smtp_username]"
                                    value="<?php echo esc_attr($settings['smtp_username']); ?>" autocomplete="off" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label
                                    for="smtp_password"><?php esc_html_e('SMTP Passwort', 'wordpress-email'); ?></label></th>
                            <td>
                                <input id="smtp_password" type="password" class="regular-text"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[smtp_password]"
                                    value="<?php echo esc_attr($settings['smtp_password']); ?>" autocomplete="new-password" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Mail-Logging', 'wordpress-email'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[log_enabled]"
                                        value="1" <?php checked($settings['log_enabled'], 1); ?> />
                                    <?php esc_html_e('Ausgehende Mails protokollieren', 'wordpress-email'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_logs_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ($this->logger === null) {
            return;
        }

        if (isset($_POST['wp_email_connector_clear_logs']) && check_admin_referer('wp_email_connector_clear_logs_action')) {
            $this->logger->clear_logs();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Logs wurden geloescht.', 'wordpress-email') . '</p></div>';
        }

        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $total = $this->logger->count_logs();
        $rows = $this->logger->get_logs($per_page, $offset);
        $total_pages = (int) ceil($total / $per_page);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Mail Logs', 'wordpress-email'); ?></h1>

            <form method="post" style="margin: 1em 0;">
                <?php wp_nonce_field('wp_email_connector_clear_logs_action'); ?>
                <input type="hidden" name="wp_email_connector_clear_logs" value="1" />
                <?php submit_button(__('Logs leeren', 'wordpress-email'), 'delete', 'submit', false); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Zeit', 'wordpress-email'); ?></th>
                        <th><?php esc_html_e('An', 'wordpress-email'); ?></th>
                        <th><?php esc_html_e('Betreff', 'wordpress-email'); ?></th>
                        <th><?php esc_html_e('Body', 'wordpress-email'); ?></th>
                        <th><?php esc_html_e('Status', 'wordpress-email'); ?></th>
                        <th><?php esc_html_e('Mailer', 'wordpress-email'); ?></th>
                        <th><?php esc_html_e('Fehler', 'wordpress-email'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e('Noch keine ausgehenden Mails protokolliert.', 'wordpress-email'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php $body_id = 'wp-email-body-' . absint($row['id']); ?>
                            <tr>
                                <td><?php echo esc_html((string) $row['created_at']); ?></td>
                                <td><?php echo esc_html((string) $row['to_email']); ?></td>
                                <td><?php echo esc_html((string) $row['subject']); ?></td>
                                <td style="min-width: 120px;">
                                    <button type="button" class="button button-small wp-email-show-body" data-target="<?php echo esc_attr($body_id); ?>">
                                        <?php esc_html_e('Body anzeigen', 'wordpress-email'); ?>
                                    </button>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'sent'): ?>
                                        <span
                                            style="color: #0a7f38; font-weight: 600;"><?php esc_html_e('Gesendet', 'wordpress-email'); ?></span>
                                    <?php else: ?>
                                        <span
                                            style="color: #b00020; font-weight: 600;"><?php esc_html_e('Fehlgeschlagen', 'wordpress-email'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html((string) $row['mailer']); ?></td>
                                <td><?php echo esc_html((string) $row['error_message']); ?></td>
                            </tr>
                            <tr id="<?php echo esc_attr($body_id); ?>" style="display: none;">
                                <td colspan="7" style="background: #f6f7f7;">
                                    <div style="margin: 8px 0 4px; font-weight: 600;"><?php esc_html_e('Vollstaendiger Mail Body', 'wordpress-email'); ?></div>
                                    <pre style="white-space: pre-wrap; word-break: break-word; max-height: 360px; overflow: auto; margin: 0;"><?php echo esc_textarea((string) $row['message']); ?></pre>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <script>
                document.addEventListener('click', function (event) {
                    var button = event.target.closest('.wp-email-show-body');
                    if (!button) {
                        return;
                    }

                    var targetId = button.getAttribute('data-target');
                    if (!targetId) {
                        return;
                    }

                    var bodyRow = document.getElementById(targetId);
                    if (!bodyRow) {
                        return;
                    }

                    var isOpen = bodyRow.style.display !== 'none';
                    bodyRow.style.display = isOpen ? 'none' : 'table-row';
                    button.textContent = isOpen ? '<?php echo esc_js(__('Body anzeigen', 'wordpress-email')); ?>' : '<?php echo esc_js(__('Body ausblenden', 'wordpress-email')); ?>';
                });
            </script>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages" style="margin: 1em 0;">
                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'current' => $current_page,
                                    'total' => $total_pages,
                                )
                            )
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_dashboard_widget()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ($this->logger === null) {
            echo '<p>' . esc_html__('Logger ist nicht initialisiert.', 'wordpress-email') . '</p>';
            return;
        }

        if ((int) $this->get('log_enabled', 1) !== 1) {
            echo '<p>' . esc_html__('Mail-Logging ist aktuell deaktiviert.', 'wordpress-email') . '</p>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=wp-email-connector-settings')) . '">' . esc_html__('Zu den Einstellungen', 'wordpress-email') . '</a></p>';
            return;
        }

        $summary = $this->logger->get_status_summary();
        $failed_since = gmdate('Y-m-d H:i:s', current_time('timestamp', true) - DAY_IN_SECONDS);
        $failed_24h = $this->logger->count_failed_since($failed_since);

        $active_filter = isset($_GET['wp_email_log_filter']) ? sanitize_key((string) $_GET['wp_email_log_filter']) : 'all';
        if (!in_array($active_filter, array('all', 'failed_24h'), true)) {
            $active_filter = 'all';
        }

        if ($active_filter === 'failed_24h') {
            $recent_logs = $this->logger->get_failed_logs_since($failed_since, 10);
        } else {
            $recent_logs = $this->logger->get_logs(10, 0);
        }

        $dashboard_url = admin_url('index.php');
        $all_url = add_query_arg('wp_email_log_filter', 'all', $dashboard_url);
        $failed_url = add_query_arg('wp_email_log_filter', 'failed_24h', $dashboard_url);

        echo '<p><strong>' . esc_html__('Gesamt', 'wordpress-email') . ':</strong> ' . esc_html((string) $summary['total']) . ' | ';
        echo '<strong>' . esc_html__('Gesendet', 'wordpress-email') . ':</strong> ' . esc_html((string) $summary['sent']) . ' | ';
        echo '<strong>' . esc_html__('Fehlgeschlagen', 'wordpress-email') . ':</strong> ' . esc_html((string) $summary['failed']) . ' | ';
        echo '<strong>' . esc_html__('Fehler 24h', 'wordpress-email') . ':</strong> ' . esc_html((string) $failed_24h) . '</p>';

        echo '<p>';
        if ($active_filter === 'all') {
            echo '<strong>' . esc_html__('Alle', 'wordpress-email') . '</strong>';
        } else {
            echo '<a href="' . esc_url($all_url) . '">' . esc_html__('Alle', 'wordpress-email') . '</a>';
        }
        echo ' | ';
        if ($active_filter === 'failed_24h') {
            echo '<strong>' . esc_html__('Fehlgeschlagen letzte 24h', 'wordpress-email') . '</strong>';
        } else {
            echo '<a href="' . esc_url($failed_url) . '">' . esc_html__('Fehlgeschlagen letzte 24h', 'wordpress-email') . '</a>';
        }
        echo '</p>';

        if (empty($recent_logs)) {
            if ($active_filter === 'failed_24h') {
                echo '<p>' . esc_html__('Keine fehlgeschlagenen Mails in den letzten 24 Stunden.', 'wordpress-email') . '</p>';
            } else {
                echo '<p>' . esc_html__('Noch keine protokollierten Mails vorhanden.', 'wordpress-email') . '</p>';
            }
        } else {
            echo '<table class="widefat striped" style="margin-bottom: 8px;">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Zeit', 'wordpress-email') . '</th>';
            echo '<th>' . esc_html__('An', 'wordpress-email') . '</th>';
            echo '<th>' . esc_html__('Betreff', 'wordpress-email') . '</th>';
            echo '<th>' . esc_html__('Status', 'wordpress-email') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($recent_logs as $log) {
                $status_label = $log['status'] === 'sent' ? __('Gesendet', 'wordpress-email') : __('Fehlgeschlagen', 'wordpress-email');
                echo '<tr>';
                echo '<td>' . esc_html((string) $log['created_at']) . '</td>';
                echo '<td>' . esc_html((string) $log['to_email']) . '</td>';
                echo '<td>' . esc_html((string) $log['subject']) . '</td>';
                echo '<td>' . esc_html($status_label) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=wp-email-connector-logs')) . '">' . esc_html__('Alle Logs anzeigen', 'wordpress-email') . '</a></p>';
    }
}
