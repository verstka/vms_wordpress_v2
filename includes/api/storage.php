<?php

if (!defined('ABSPATH')) {
    exit;
}

function vms_v2_save_media(string $filename, string $temp_path, string $material_id): string
{
    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        throw new RuntimeException('Upload directory error: ' . $upload['error']);
    }

    $relative = vms_v2_get_media_subdir() . '/' . $material_id;
    $target_dir = trailingslashit($upload['basedir']) . $relative;
    wp_mkdir_p($target_dir);

    if (!wp_is_writable($target_dir)) {
        throw new RuntimeException('Media directory is not writable');
    }

    $target_path = trailingslashit($target_dir) . $filename;
    if (!copy($temp_path, $target_path)) {
        throw new RuntimeException('Failed to copy media file');
    }

    return trailingslashit($upload['baseurl']) . $relative . '/' . $filename;
}

function vms_v2_save_font_file(string $filename, string $temp_path, string $material_id): string
{
    unset($material_id);

    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        throw new RuntimeException('Upload directory error: ' . $upload['error']);
    }

    $relative = vms_v2_get_fonts_subdir();
    $target_dir = trailingslashit($upload['basedir']) . $relative;
    wp_mkdir_p($target_dir);

    if (!wp_is_writable($target_dir)) {
        throw new RuntimeException('Fonts directory is not writable');
    }

    $target_path = trailingslashit($target_dir) . $filename;
    if (!copy($temp_path, $target_path)) {
        throw new RuntimeException('Failed to copy font file');
    }

    return trailingslashit($upload['baseurl']) . $relative . '/' . $filename;
}

function vms_v2_save_fonts_manifest(string $filename, string $temp_path, string $material_id): string
{
    return vms_v2_save_font_file($filename, $temp_path, $material_id);
}

function vms_v2_get_fonts_dir(): string
{
    $upload = wp_upload_dir();
    return trailingslashit($upload['basedir']) . vms_v2_get_fonts_subdir();
}
