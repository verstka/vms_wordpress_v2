<?php

if (!defined('ABSPATH')) {
    exit;
}

function vms_v2_register_settings_hooks(): void
{
    add_action('admin_menu', 'vms_v2_add_settings_page');
    add_action('admin_init', 'vms_v2_register_settings');
    add_action('admin_post_vms_v2_reset_credentials', 'vms_v2_reset_credentials_callback');
    add_action('admin_post_vms_v2_save_extra_settings', 'vms_v2_save_extra_settings_callback');
    add_action('admin_enqueue_scripts', 'vms_v2_enqueue_admin_assets');
    add_action('wp_ajax_vms_v2_toggle_dev_mode', 'vms_v2_toggle_dev_mode_callback');
    add_action('wp_ajax_vms_v2_toggle_verify_callback_user_email', 'vms_v2_toggle_verify_callback_user_email_callback');
    add_action('wp_ajax_vms_v2_flush_permalinks', 'vms_v2_flush_permalinks_callback');
}

function vms_v2_add_settings_page(): void
{
    add_options_page(
        __('Verstka Backend v2 Settings', 'verstka-backend-v2'),
        __('Verstka Backend v2', 'verstka-backend-v2'),
        'manage_options',
        'verstka-backend-v2-settings',
        'vms_v2_render_settings_page'
    );
}

function vms_v2_register_settings(): void
{
    register_setting('vms_v2_credentials_group', 'vms_v2_api_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('vms_v2_credentials_group', 'vms_v2_api_secret', ['sanitize_callback' => 'sanitize_text_field']);
}

/**
 * @return array<string, string>
 */
function vms_v2_get_settings_field_labels(): array
{
    return [
        'vms_v2_api_key' => __('API Key', 'verstka-backend-v2'),
        'vms_v2_api_secret' => __('API Secret', 'verstka-backend-v2'),
        'vms_v2_api_url' => __('API URL', 'verstka-backend-v2'),
        'vms_v2_callback_url' => __('Callback URL', 'verstka-backend-v2'),
        'vms_v2_viewer_script_url' => __('Viewer Script URL', 'verstka-backend-v2'),
        'vms_v2_media_subdir' => __('Media Subdirectory (uploads)', 'verstka-backend-v2'),
        'vms_v2_fonts_subdir' => __('Fonts Subdirectory (uploads)', 'verstka-backend-v2'),
        'vms_v2_basic_auth_user' => __('Webhook Basic Auth User', 'verstka-backend-v2'),
        'vms_v2_basic_auth_password' => __('Webhook Basic Auth Password', 'verstka-backend-v2'),
        'vms_v2_max_content_size_mb' => __('Max Content Size (MB)', 'verstka-backend-v2'),
        'vms_v2_dev_mode' => __('Dev Mode', 'verstka-backend-v2'),
        'vms_v2_verify_callback_user_email' => __('Verify Callback User Email', 'verstka-backend-v2'),
    ];
}

/**
 * @return array<string, array{action: string, param: string}>
 */
function vms_v2_get_independent_toggle_fields(): array
{
    return [
        'vms_v2_dev_mode' => [
            'action' => 'vms_v2_toggle_dev_mode',
            'param' => 'dev_mode',
        ],
        'vms_v2_verify_callback_user_email' => [
            'action' => 'vms_v2_toggle_verify_callback_user_email',
            'param' => 'verify_callback_user_email',
        ],
    ];
}

/**
 * @param array{id: string, lock_saved?: bool} $args
 */
function vms_v2_render_settings_field(array $args): void
{
    $id = $args['id'];
    $lock_saved = !empty($args['lock_saved']);
    $defaults = [
        'vms_v2_api_url' => VMS_V2_DEFAULT_API_URL,
        'vms_v2_callback_url' => vms_v2_get_callback_url(),
        'vms_v2_viewer_script_url' => VMS_V2_DEFAULT_VIEWER_SCRIPT_URL,
        'vms_v2_media_subdir' => VMS_V2_DEFAULT_MEDIA_SUBDIR,
        'vms_v2_fonts_subdir' => VMS_V2_DEFAULT_FONTS_SUBDIR,
    ];

    $independent_toggles = vms_v2_get_independent_toggle_fields();
    if (isset($independent_toggles[$id])) {
        $toggle = $independent_toggles[$id];
        $value = (int) get_option($id, 0);
        printf(
            '<input type="checkbox" id="%1$s" class="vms-v2-independent-toggle" value="1" data-action="%2$s" data-param="%3$s" %4$s />',
            esc_attr($id),
            esc_attr($toggle['action']),
            esc_attr($toggle['param']),
            checked(1, $value, false)
        );
        if ($id === 'vms_v2_verify_callback_user_email') {
            echo '<p class="description">';
            esc_html_e(
                'When enabled, the editor user\'s email must match an existing site user with permission to edit posts.',
                'verstka-backend-v2'
            );
            echo '</p>';
        }
        return;
    }

    if ($id === 'vms_v2_max_content_size_mb') {
        printf(
            '<input type="number" name="%1$s" id="%1$s" value="%2$s" class="small-text" min="1" />',
            esc_attr($id),
            esc_attr((string) vms_v2_get_max_content_size_mb())
        );
        return;
    }

    $value = get_option($id, $defaults[$id] ?? '');
    $type = in_array($id, ['vms_v2_api_secret', 'vms_v2_basic_auth_password'], true) ? 'password' : 'text';
    $saved_key = get_option('vms_v2_api_key');
    $disabled = ($lock_saved && $saved_key && in_array($id, ['vms_v2_api_key', 'vms_v2_api_secret'], true)) ? 'disabled' : '';

    printf(
        '<input type="%s" name="%s" id="%s" value="%s" class="regular-text" %s />',
        esc_attr($type),
        esc_attr($id),
        esc_attr($id),
        esc_attr((string) $value),
        $disabled
    );
}

/**
 * @param array{lock_saved?: bool} $options
 */
function vms_v2_render_settings_row(string $id, array $options = []): void
{
    $labels = vms_v2_get_settings_field_labels();
    $label = $labels[$id] ?? $id;
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <?php vms_v2_render_settings_field(['id' => $id, 'lock_saved' => !empty($options['lock_saved'])]); ?>
        </td>
    </tr>
    <?php
}

function vms_v2_render_settings_page(): void
{
    $saved_key = get_option('vms_v2_api_key');
    $rest_test_url = rest_url('verstka/v2/test');
    $callback_url = vms_v2_get_callback_url();

    $extra_fields = [
        'vms_v2_api_url',
        'vms_v2_callback_url',
        'vms_v2_viewer_script_url',
        'vms_v2_media_subdir',
        'vms_v2_fonts_subdir',
        'vms_v2_basic_auth_user',
        'vms_v2_basic_auth_password',
        'vms_v2_max_content_size_mb',
    ];
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Verstka Backend v2 Settings', 'verstka-backend-v2'); ?></h1>

        <div class="notice notice-info verstka-settings-section">
            <h3><?php esc_html_e('REST API Diagnostics', 'verstka-backend-v2'); ?></h3>
            <p><strong><?php esc_html_e('Test URL:', 'verstka-backend-v2'); ?></strong>
                <a href="<?php echo esc_url($rest_test_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($rest_test_url); ?></a>
            </p>
            <p><strong><?php esc_html_e('Callback URL:', 'verstka-backend-v2'); ?></strong> <?php echo esc_html($callback_url); ?></p>
            <p>
                <button type="button" id="vms-v2-test-api" class="button button-secondary"><?php esc_html_e('Test REST API', 'verstka-backend-v2'); ?></button>
                <button type="button" id="vms-v2-flush-permalinks" class="button button-secondary"><?php esc_html_e('Reset Permalinks', 'verstka-backend-v2'); ?></button>
                <span id="vms-v2-api-status"></span>
            </p>
        </div>

        <div class="verstka-settings-section vms-v2-settings-block">
            <h2><?php esc_html_e('Credentials', 'verstka-backend-v2'); ?></h2>
            <form action="options.php" method="post">
                <?php settings_fields('vms_v2_credentials_group'); ?>
                <table class="form-table">
                    <?php
                    vms_v2_render_settings_row('vms_v2_api_key', ['lock_saved' => true]);
                    vms_v2_render_settings_row('vms_v2_api_secret', ['lock_saved' => true]);
                    ?>
                </table>
                <?php if (!$saved_key) : ?>
                    <div class="vms-v2-settings-actions">
                        <?php submit_button(__('Save Credentials', 'verstka-backend-v2')); ?>
                    </div>
                <?php endif; ?>
            </form>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="vms-v2-settings-actions">
                <?php wp_nonce_field('vms_v2_reset_credentials_action', 'vms_v2_reset_credentials_nonce'); ?>
                <input type="hidden" name="action" value="vms_v2_reset_credentials">
                <?php submit_button(__('Reset Credentials', 'verstka-backend-v2'), 'secondary', 'vms_v2_reset_submit', false); ?>
            </form>
        </div>

        <div class="verstka-settings-section vms-v2-settings-block">
            <h2><?php esc_html_e('Additional Settings', 'verstka-backend-v2'); ?></h2>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <?php wp_nonce_field('vms_v2_save_extra_settings_action', 'vms_v2_save_extra_settings_nonce'); ?>
                <input type="hidden" name="action" value="vms_v2_save_extra_settings">
                <table class="form-table">
                    <?php foreach ($extra_fields as $field_id) : ?>
                        <?php vms_v2_render_settings_row($field_id); ?>
                    <?php endforeach; ?>
                </table>
                <div class="vms-v2-settings-actions">
                    <?php submit_button(__('Save Additional Settings', 'verstka-backend-v2')); ?>
                </div>
            </form>
        </div>

        <div class="verstka-settings-section vms-v2-settings-block">
            <h2><?php esc_html_e('Independent Settings', 'verstka-backend-v2'); ?></h2>
            <table class="form-table">
                <?php vms_v2_render_settings_row('vms_v2_dev_mode'); ?>
                <?php vms_v2_render_settings_row('vms_v2_verify_callback_user_email'); ?>
            </table>
        </div>
    </div>
    <?php
}

function vms_v2_reset_credentials_callback(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'verstka-backend-v2'));
    }
    check_admin_referer('vms_v2_reset_credentials_action', 'vms_v2_reset_credentials_nonce');

    delete_option('vms_v2_api_key');
    delete_option('vms_v2_api_secret');

    wp_safe_redirect(admin_url('options-general.php?page=verstka-backend-v2-settings'));
    exit;
}

function vms_v2_save_extra_settings_callback(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'verstka-backend-v2'));
    }
    check_admin_referer('vms_v2_save_extra_settings_action', 'vms_v2_save_extra_settings_nonce');

    $url_fields = ['vms_v2_api_url', 'vms_v2_callback_url', 'vms_v2_viewer_script_url'];
    foreach ($url_fields as $field) {
        if (isset($_POST[$field])) {
            update_option($field, esc_url_raw(wp_unslash($_POST[$field])));
        }
    }

    $text_fields = [
        'vms_v2_media_subdir',
        'vms_v2_fonts_subdir',
        'vms_v2_basic_auth_user',
        'vms_v2_basic_auth_password',
    ];
    foreach ($text_fields as $field) {
        if (isset($_POST[$field])) {
            update_option($field, sanitize_text_field(wp_unslash($_POST[$field])));
        }
    }

    $mb = isset($_POST['vms_v2_max_content_size_mb']) ? (int) $_POST['vms_v2_max_content_size_mb'] : 0;
    update_option('vms_v2_max_content_size', vms_v2_bytes_from_mb($mb > 0 ? $mb : 100));

    wp_safe_redirect(admin_url('options-general.php?page=verstka-backend-v2-settings'));
    exit;
}

function vms_v2_enqueue_admin_assets(string $hook): void
{
    wp_enqueue_style(
        'vms-v2-admin-style',
        VMS_V2_PLUGIN_URL . 'assets/css/vms_admin.css',
        [],
        VMS_V2_VERSION
    );

    if ($hook === 'settings_page_verstka-backend-v2-settings') {
        wp_enqueue_script(
            'vms-v2-admin-script',
            VMS_V2_PLUGIN_URL . 'assets/js/vms_settings.js',
            ['jquery'],
            VMS_V2_VERSION,
            true
        );
        wp_localize_script('vms-v2-admin-script', 'vmsV2Settings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vms_v2_dev_mode_nonce'),
            'flushNonce' => wp_create_nonce('vms_v2_flush_permalinks'),
            'restTestUrl' => rest_url('verstka/v2/test'),
            'strings' => [
                'testing' => __('Testing...', 'verstka-backend-v2'),
                'testRestApi' => __('Test REST API', 'verstka-backend-v2'),
                'resetPermalinks' => __('Reset Permalinks', 'verstka-backend-v2'),
                'resetting' => __('Resetting...', 'verstka-backend-v2'),
            ],
        ]);
    }

    if ($hook === 'edit.php') {
        wp_enqueue_script(
            'vms-v2-toggle-script',
            VMS_V2_PLUGIN_URL . 'assets/js/vms_toggle.js',
            ['jquery'],
            VMS_V2_VERSION,
            true
        );
        wp_localize_script('vms-v2-toggle-script', 'vmsV2Toggle', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }
}

function vms_v2_toggle_dev_mode_callback(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied', 'verstka-backend-v2')]);
    }
    check_ajax_referer('vms_v2_dev_mode_nonce', 'security');

    $dev_mode = isset($_POST['dev_mode']) && $_POST['dev_mode'] === '1' ? 1 : 0;
    update_option('vms_v2_dev_mode', $dev_mode);

    wp_send_json_success(['dev_mode' => $dev_mode]);
}

function vms_v2_toggle_verify_callback_user_email_callback(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied', 'verstka-backend-v2')]);
    }
    check_ajax_referer('vms_v2_dev_mode_nonce', 'security');

    $enabled = isset($_POST['verify_callback_user_email']) && $_POST['verify_callback_user_email'] === '1' ? 1 : 0;
    update_option('vms_v2_verify_callback_user_email', $enabled);

    wp_send_json_success(['verify_callback_user_email' => $enabled]);
}

function vms_v2_flush_permalinks_callback(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied', 'verstka-backend-v2')]);
    }
    check_ajax_referer('vms_v2_flush_permalinks', 'nonce');

    flush_rewrite_rules();
    wp_send_json_success(['message' => __('Permalinks flushed successfully', 'verstka-backend-v2')]);
}
