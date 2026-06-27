<?php

if (!defined('ABSPATH')) {
    exit;
}

function vms_v2_register_editor_hooks(): void
{
    add_action('admin_menu', 'vms_v2_add_editor_page');
}

function vms_v2_add_editor_page(): void
{
    add_submenu_page(
        null,
        __('Verstka Editor', 'verstka-backend-v2'),
        __('Verstka Editor', 'verstka-backend-v2'),
        'edit_posts',
        'verstka-v2-editor',
        'vms_v2_editor_open'
    );
}

function vms_v2_get_editor_url(int $post_id): string
{
    return add_query_arg(
        [
            'page' => 'verstka-v2-editor',
            'post' => $post_id,
        ],
        admin_url('admin.php')
    );
}

function vms_v2_editor_open(): void
{
    if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('Permission denied', 'verstka-backend-v2'));
    }

    $material_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    $post = get_post($material_id);
    if (!$post) {
        wp_die(esc_html__('Invalid post ID', 'verstka-backend-v2'));
    }

    $vms_json = vms_v2_load_post_vms_json($material_id);
    $metadata = vms_v2_build_editor_metadata($material_id);

    $editor_url = vms_v2_session_open((string) $material_id, $vms_json, $metadata);

    if (is_wp_error($editor_url)) {
        $message = $editor_url->get_error_message();
        if (vms_v2_is_dev_mode()) {
            wp_die(
                '<pre>' . esc_html(wp_json_encode([
                    'message' => $message,
                    'materialId' => $material_id,
                    'callbackUrl' => vms_v2_get_callback_url(),
                    'apiUrl' => vms_v2_get_session_open_url(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>'
            );
        }

        echo '<script>window.onload=function(){alert(' . wp_json_encode($message) . ');history.back();};</script>';
        return;
    }

    $parts = wp_parse_url($editor_url);
    $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
    $has_host = is_array($parts) && !empty($parts['host']);
    $is_https = $scheme === 'https';
    $is_dev_http = vms_v2_is_dev_mode() && $scheme === 'http';

    if (!$has_host || (!$is_https && !$is_dev_http)) {
        wp_die(esc_html__('Invalid editor URL returned by Verstka API', 'verstka-backend-v2'));
    }

    $redirect_url = esc_url_raw($editor_url);
    wp_die(
        '<script>window.location.replace(' . wp_json_encode($redirect_url) . ');</script>' .
        '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr($redirect_url) . '"></noscript>',
        '',
        ['response' => 200]
    );
}
