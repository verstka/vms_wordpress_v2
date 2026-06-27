<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array{media: array<string, string>, vms_json: string|null, vms_html: string|null, temp_dir: string}
 */
function vms_v2_extract_content_zip(string $zip_path, string $temp_dir): array
{
    $media_files = [];
    $vms_json_content = null;
    $vms_html_content = null;
    $temp_dir_abs = realpath($temp_dir) ?: $temp_dir;

    $media_extensions = [
        '.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg', '.ico', '.avif',
        '.mp4', '.webm', '.ogv',
        '.mp3', '.wav', '.ogg', '.aac', '.m4a',
        '.pdf', '.txt',
        '.json', '.lottie',
    ];

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        return [
            'media' => [],
            'vms_json' => null,
            'vms_html' => null,
            'temp_dir' => $temp_dir_abs,
        ];
    }

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);
        if ($stat === false || str_ends_with($stat['name'], '/')) {
            continue;
        }

        $filename = $stat['name'];
        if (!vms_v2_is_safe_zip_member($filename)) {
            continue;
        }

        $normalized = str_replace('\\', '/', $filename);

        if ($normalized === 'vms_json.json') {
            $content = $zip->getFromIndex($index);
            if (is_string($content)) {
                $vms_json_content = $content;
            }
            continue;
        }

        if ($normalized === 'vms_html.html') {
            $content = $zip->getFromIndex($index);
            if (is_string($content)) {
                $vms_html_content = $content;
            }
            continue;
        }

        if (!str_starts_with($normalized, 'vms_media/')) {
            continue;
        }

        $basename = basename($normalized);
        if ($basename === '') {
            continue;
        }

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array('.' . $extension, $media_extensions, true)) {
            continue;
        }

        if (!$zip->extractTo($temp_dir_abs, [$filename])) {
            continue;
        }

        $absolute_path = realpath($temp_dir_abs . '/' . $filename) ?: ($temp_dir_abs . '/' . $filename);
        if (!str_starts_with($absolute_path, $temp_dir_abs)) {
            @unlink($absolute_path);
            continue;
        }

        $media_files[$basename] = $absolute_path;
    }

    $zip->close();

    return [
        'media' => $media_files,
        'vms_json' => $vms_json_content,
        'vms_html' => $vms_html_content,
        'temp_dir' => $temp_dir_abs,
    ];
}

/**
 * @return array{font_files: array<string, string>, vms_fonts_json_path: string|null, vms_fonts_css_path: string|null, temp_dir: string}
 */
function vms_v2_extract_fonts_zip(string $zip_path, string $temp_dir): array
{
    $font_files = [];
    $vms_fonts_json_path = null;
    $vms_fonts_css_path = null;
    $temp_dir_abs = realpath($temp_dir) ?: $temp_dir;

    $font_extensions = ['.woff', '.woff2', '.ttf', '.otf', '.eot'];

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        return [
            'font_files' => [],
            'vms_fonts_json_path' => null,
            'vms_fonts_css_path' => null,
            'temp_dir' => $temp_dir_abs,
        ];
    }

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);
        if ($stat === false || str_ends_with($stat['name'], '/')) {
            continue;
        }

        $filename = $stat['name'];
        if (!vms_v2_is_safe_zip_member($filename)) {
            continue;
        }

        $normalized = str_replace('\\', '/', $filename);
        $basename = basename($normalized);
        if ($basename === '') {
            continue;
        }

        if ($normalized === 'vms_fonts.json') {
            $zip->extractTo($temp_dir_abs, [$filename]);
            $vms_fonts_json_path = realpath($temp_dir_abs . '/' . $filename) ?: ($temp_dir_abs . '/' . $filename);
            continue;
        }

        if ($normalized === 'vms_fonts.css') {
            $zip->extractTo($temp_dir_abs, [$filename]);
            $vms_fonts_css_path = realpath($temp_dir_abs . '/' . $filename) ?: ($temp_dir_abs . '/' . $filename);
            continue;
        }

        if (!str_starts_with($normalized, 'vms_fonts/')) {
            continue;
        }

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array('.' . $extension, $font_extensions, true)) {
            continue;
        }

        $target_dir = $temp_dir_abs . '/vms_fonts';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0775, true);
        }

        $target = $target_dir . '/' . $basename;
        $content = $zip->getFromIndex($index);
        if (!is_string($content)) {
            continue;
        }
        file_put_contents($target, $content);

        $absolute_path = realpath($target) ?: $target;
        if (!str_starts_with($absolute_path, $temp_dir_abs)) {
            @unlink($absolute_path);
            continue;
        }

        $font_files[$basename] = $absolute_path;
    }

    $zip->close();

    return [
        'font_files' => $font_files,
        'vms_fonts_json_path' => $vms_fonts_json_path,
        'vms_fonts_css_path' => $vms_fonts_css_path,
        'temp_dir' => $temp_dir_abs,
    ];
}

function vms_v2_is_safe_zip_member(string $name): bool
{
    return !str_contains($name, '..') && !str_starts_with($name, '/');
}

function vms_v2_make_temp_dir(string $prefix): string
{
    $temp_dir = sys_get_temp_dir() . '/' . $prefix . '_' . bin2hex(random_bytes(8));
    if (!mkdir($temp_dir, 0775, true) && !is_dir($temp_dir)) {
        throw new RuntimeException('Failed to create temp directory');
    }

    return $temp_dir;
}

function vms_v2_cleanup_temp_dir(?string $temp_dir): void
{
    if ($temp_dir === null || $temp_dir === '' || !is_dir($temp_dir)) {
        return;
    }

    $items = scandir($temp_dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $temp_dir . '/' . $item;
        if (is_dir($path)) {
            vms_v2_cleanup_temp_dir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($temp_dir);
}
