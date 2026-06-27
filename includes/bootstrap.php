<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once VMS_V2_PLUGIN_DIR . 'includes/polyfills.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/helpers.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/activation.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/api/signature.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/api/url-builder.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/api/http.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/api/storage.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/api/zip-extractor.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/api/session.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/api/callback-processor.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/rest.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/editor.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/settings.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/post-ui.php';
require_once VMS_V2_PLUGIN_DIR . 'includes/frontend.php';

/**
 * Initialize plugin after WordPress loads.
 */
function vms_v2_init(): void
{
    load_plugin_textdomain(
        'verstka-backend-v2',
        false,
        dirname(plugin_basename(VMS_V2_PLUGIN_FILE)) . '/languages'
    );

    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        add_action('admin_notices', 'vms_v2_wp_version_notice');
        return;
    }

    if (!function_exists('rest_url')) {
        add_action('admin_notices', 'vms_v2_rest_api_notice');
        return;
    }

    $missing_extensions = vms_v2_missing_extensions();
    if ($missing_extensions !== []) {
        add_action('admin_notices', static function () use ($missing_extensions): void {
            vms_v2_extensions_notice($missing_extensions);
        });
        return;
    }

    vms_v2_register_rest_routes();
    vms_v2_register_settings_hooks();
    vms_v2_register_editor_hooks();
    vms_v2_register_post_ui_hooks();
    vms_v2_register_frontend_hooks();
}

function vms_v2_missing_extensions(): array
{
    $required = ['json', 'hash', 'zip'];
    $missing = [];
    foreach ($required as $extension) {
        if (!extension_loaded($extension)) {
            $missing[] = $extension;
        }
    }

    return $missing;
}

function vms_v2_wp_version_notice(): void
{
    echo '<div class="notice notice-error"><p>';
    echo esc_html(
        sprintf(
            /* translators: %s: WordPress version */
            __('Verstka Backend v2 requires WordPress 5.0 or higher. You are running version %s.', 'verstka-backend-v2'),
            get_bloginfo('version')
        )
    );
    echo '</p></div>';
}

function vms_v2_rest_api_notice(): void
{
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Verstka Backend v2 requires the WordPress REST API.', 'verstka-backend-v2');
    echo '</p></div>';
}

/**
 * @param string[] $missing_extensions
 */
function vms_v2_extensions_notice(array $missing_extensions): void
{
    echo '<div class="notice notice-error"><p>';
    echo esc_html(
        sprintf(
            /* translators: %s: comma-separated PHP extension names */
            __('Verstka Backend v2 requires PHP extensions: %s.', 'verstka-backend-v2'),
            implode(', ', $missing_extensions)
        )
    );
    echo '</p></div>';
}
