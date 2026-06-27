<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activation: add custom post columns and flush rewrite rules.
 */
function vms_v2_activate(): void
{
    global $wpdb;

    $table = $wpdb->posts;
    $columns = [
        'post_isvms' => [
            'definition' => 'BOOLEAN NOT NULL DEFAULT 0',
            'after' => 'post_date_gmt',
        ],
        'post_vms_content' => [
            'definition' => 'LONGTEXT NULL',
            'after' => 'post_content',
        ],
        'post_vms_json' => [
            'definition' => 'LONGTEXT NULL',
            'after' => 'post_vms_content',
        ],
    ];

    foreach ($columns as $column => $attrs) {
        $exists = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column)
        );
        if ($exists !== $column) {
            $definition = $attrs['definition'];
            $after = !empty($attrs['after']) ? " AFTER `{$attrs['after']}`" : '';
            $wpdb->query("ALTER TABLE `{$table}` ADD `{$column}` {$definition}{$after}");
        }
    }

    if (get_option('vms_v2_max_content_size', false) === false) {
        add_option('vms_v2_max_content_size', vms_v2_parse_php_memory_limit_bytes(), '', 'no');
    }

    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook placeholder.
 */
function vms_v2_deactivate(): void
{
    // Reserved for future cleanup.
}
