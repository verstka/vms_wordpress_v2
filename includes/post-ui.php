<?php

if (!defined('ABSPATH')) {
    exit;
}

function vms_v2_register_post_ui_hooks(): void
{
    add_filter('manage_edit-post_columns', 'vms_v2_add_post_isvms_column', 4);
    add_filter('manage_edit-page_columns', 'vms_v2_add_post_isvms_column', 4);
    add_filter('manage_post_posts_custom_column', 'vms_v2_fill_post_isvms_column', 5, 2);
    add_filter('manage_page_posts_custom_column', 'vms_v2_fill_post_isvms_column', 5, 2);
    add_filter('post_row_actions', 'vms_v2_add_row_actions', 10, 2);
    add_filter('page_row_actions', 'vms_v2_add_row_actions', 10, 2);
    add_filter('manage_edit-post_sortable_columns', 'vms_v2_sortable_vms_column');
    add_filter('manage_edit-page_sortable_columns', 'vms_v2_sortable_vms_column');
    add_action('pre_get_posts', 'vms_v2_orderby_vms_column');
    add_filter('edit_post_link', 'vms_v2_replace_edit_post_link', 10, 3);
    add_action('enqueue_block_editor_assets', 'vms_v2_enqueue_block_editor_buttons');
    add_action('media_buttons', 'vms_v2_add_classic_editor_button', 11);
    add_action('wp_ajax_vms_v2_toggle_vms', 'vms_v2_ajax_toggle_vms');
}

/**
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function vms_v2_add_post_isvms_column(array $columns): array
{
    $result = [];
    foreach ($columns as $name => $value) {
        if ($name === 'title') {
            $result['post_isvms'] = 'Ѵ';
        }
        $result[$name] = $value;
    }

    return $result;
}

function vms_v2_fill_post_isvms_column(string $column_name, int $post_id): void
{
    if ($column_name !== 'post_isvms') {
        return;
    }

    $post = get_post($post_id);
    $star = !empty($post->post_isvms) ? '&#9733;' : '&#9734;';
    $nonce = wp_create_nonce('vms_v2_toggle_vms');

    printf(
        '<a href="#" class="vms-v2-toggle" data-post-id="%d" data-nonce="%s" title="%s">%s</a>',
        $post_id,
        esc_attr($nonce),
        esc_attr__('Toggle Verstka', 'verstka-backend-v2'),
        $star
    );
}

/**
 * @param array<string, string> $actions
 * @return array<string, string>
 */
function vms_v2_add_row_actions(array $actions, WP_Post $post): array
{
    if (!in_array($post->post_type, ['post', 'page'], true)) {
        return $actions;
    }

    $editor_url = vms_v2_get_editor_url((int) $post->ID);
    $actions['vms_v2_edit'] = sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url($editor_url),
        esc_html__('Edit in Verstka', 'verstka-backend-v2')
    );

    return $actions;
}

/**
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function vms_v2_sortable_vms_column(array $columns): array
{
    $columns['post_isvms'] = 'post_isvms';
    return $columns;
}

function vms_v2_orderby_vms_column(WP_Query $query): void
{
    if (!is_admin() || $query->get('orderby') !== 'post_isvms') {
        return;
    }

    $post_type = $query->get('post_type');
    if (!in_array($post_type, ['post', 'page'], true)) {
        return;
    }

    $query->set('orderby', 'post_isvms');
    $order = strtoupper((string) $query->get('order')) === 'ASC' ? 'ASC' : 'DESC';
    $query->set('order', $order);
}

function vms_v2_replace_edit_post_link(string $link, int $post_id, string $text): string
{
    unset($text);

    $post = get_post($post_id);
    if (!$post || empty($post->post_isvms)) {
        return $link;
    }

    $editor_url = vms_v2_get_editor_url($post_id);

    return sprintf(
        '<a href="%s" class="vms-v2-edit-button" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url($editor_url),
        esc_html__('Edit in Verstka', 'verstka-backend-v2')
    );
}

function vms_v2_enqueue_block_editor_buttons(): void
{
    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if ($post_id <= 0) {
        return;
    }

    wp_enqueue_script(
        'vms-v2-block-editor',
        VMS_V2_PLUGIN_URL . 'assets/js/vms_block_editor.js',
        ['wp-plugins', 'wp-edit-post', 'wp-components', 'wp-element', 'wp-i18n'],
        VMS_V2_VERSION,
        true
    );

    wp_localize_script('vms-v2-block-editor', 'vmsV2BlockEditor', [
        'editorUrl' => vms_v2_get_editor_url($post_id),
        'label' => __('Edit in Verstka', 'verstka-backend-v2'),
    ]);
}

function vms_v2_add_classic_editor_button(string $editor_id): void
{
    unset($editor_id);

    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if ($post_id <= 0) {
        return;
    }

    $editor_url = vms_v2_get_editor_url($post_id);
    printf(
        '<a href="%s" class="button" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url($editor_url),
        esc_html__('Edit in Verstka', 'verstka-backend-v2')
    );
}

function vms_v2_ajax_toggle_vms(): void
{
    check_ajax_referer('vms_v2_toggle_vms', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied', 'verstka-backend-v2')]);
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ($post_id <= 0) {
        wp_send_json_error(['message' => __('Invalid post ID', 'verstka-backend-v2')]);
    }

    global $wpdb;
    $current = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT post_isvms FROM {$wpdb->posts} WHERE ID = %d", $post_id)
    );
    $new = $current ? 0 : 1;

    $wpdb->update(
        $wpdb->posts,
        ['post_isvms' => $new],
        ['ID' => $post_id]
    );
    clean_post_cache($post_id);

    wp_send_json_success(['post_isvms' => $new]);
}
