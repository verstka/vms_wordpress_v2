<?php

if (!defined('ABSPATH')) {
    exit;
}

const VMS_V2_DEFAULT_API_URL = 'https://api.r2.verstka.org/integration';
const VMS_V2_DEFAULT_VIEWER_SCRIPT_URL = 'https://go.r2.verstka.org/viewer-latest.js';
const VMS_V2_DEFAULT_MEDIA_SUBDIR = 'verstka/materials';
const VMS_V2_DEFAULT_FONTS_SUBDIR = 'verstka/fonts';
const VMS_V2_DEFAULT_MAX_CONTENT_SIZE = 104857600;
const VMS_V2_FONTS_CALLBACK_EVENT = 'site_fonts_updated';

function vms_v2_get_api_key(): string
{
    return (string) get_option('vms_v2_api_key', '');
}

function vms_v2_get_api_secret(): string
{
    return (string) get_option('vms_v2_api_secret', '');
}

function vms_v2_get_api_url(): string
{
    $url = (string) get_option('vms_v2_api_url', VMS_V2_DEFAULT_API_URL);
    return rtrim($url, '/');
}

function vms_v2_get_callback_url(): string
{
    $configured = (string) get_option('vms_v2_callback_url', '');
    if ($configured !== '') {
        return $configured;
    }

    return rest_url('verstka/v2/callback');
}

function vms_v2_get_viewer_script_url(): string
{
    $url = (string) get_option('vms_v2_viewer_script_url', VMS_V2_DEFAULT_VIEWER_SCRIPT_URL);
    return $url !== '' ? $url : VMS_V2_DEFAULT_VIEWER_SCRIPT_URL;
}

function vms_v2_get_media_subdir(): string
{
    $subdir = (string) get_option('vms_v2_media_subdir', VMS_V2_DEFAULT_MEDIA_SUBDIR);
    return trim($subdir, '/');
}

function vms_v2_get_fonts_subdir(): string
{
    $subdir = (string) get_option('vms_v2_fonts_subdir', VMS_V2_DEFAULT_FONTS_SUBDIR);
    return trim($subdir, '/');
}

function vms_v2_get_fonts_css_url(): string
{
    return (string) get_option('vms_v2_fonts_css_url', '');
}

function vms_v2_is_dev_mode(): bool
{
    return (int) get_option('vms_v2_dev_mode', 0) === 1;
}

function vms_v2_should_verify_callback_user_email(): bool
{
    return (int) get_option('vms_v2_verify_callback_user_email', 0) === 1;
}

function vms_v2_get_basic_auth_user(): string
{
    return (string) get_option('vms_v2_basic_auth_user', '');
}

function vms_v2_get_basic_auth_password(): string
{
    return (string) get_option('vms_v2_basic_auth_password', '');
}

function vms_v2_get_max_content_size(): int
{
    $size = (int) get_option('vms_v2_max_content_size', VMS_V2_DEFAULT_MAX_CONTENT_SIZE);
    return $size > 0 ? $size : VMS_V2_DEFAULT_MAX_CONTENT_SIZE;
}

function vms_v2_parse_php_memory_limit_bytes(): int
{
    if (!function_exists('wp_convert_hr_to_bytes')) {
        require_once ABSPATH . WPINC . '/functions.php';
    }
    $bytes = (int) wp_convert_hr_to_bytes(ini_get('memory_limit'));
    if ($bytes <= 0) {
        return VMS_V2_DEFAULT_MAX_CONTENT_SIZE;
    }
    return $bytes;
}

function vms_v2_get_max_content_size_mb(): int
{
    return (int) round(vms_v2_get_max_content_size() / 1048576);
}

function vms_v2_bytes_from_mb(int $mb): int
{
    return max(1, $mb) * 1048576;
}

function vms_v2_get_request_timeout(): int
{
    return 60;
}

function vms_v2_get_download_timeout(): int
{
    return 120;
}

function vms_v2_get_session_open_url(): string
{
    return vms_v2_get_api_url() . '/session/open';
}

/**
 * @return array{rc: int, rm: string, data: array<string, mixed>}
 */
function vms_v2_form_json(int $rc, string $rm, array $data = []): WP_REST_Response
{
    return rest_ensure_response([
        'rc' => $rc,
        'rm' => $rm,
        'data' => $data,
    ]);
}

/**
 * @param array<string, mixed>|string|null $value
 * @return array<string, mixed>|null
 */
function vms_v2_coerce_json_array($value): ?array
{
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        if ($value === '') {
            return null;
        }
        $parsed = json_decode($value, true);
        if (!is_array($parsed)) {
            return null;
        }
        return $parsed;
    }

    return $value;
}

function vms_v2_is_fonts_callback(array $payload): bool
{
    return ($payload['event'] ?? null) === VMS_V2_FONTS_CALLBACK_EVENT;
}
