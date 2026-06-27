<?php

if (!defined('ABSPATH')) {
    exit;
}

const VMS_V2_SESSION_OPEN_CACHE_TTL = 10;
const VMS_V2_SESSION_OPEN_LOCK_TTL = 10;
const VMS_V2_SESSION_OPEN_WAIT_ATTEMPTS = 20;
const VMS_V2_SESSION_OPEN_WAIT_US = 100000;

/**
 * Open Verstka editor session and return editor URL.
 *
 * @param array<string, mixed>|string|null $vms_json
 * @param array<string, mixed>|string|null $metadata
 * @return string|WP_Error
 */
function vms_v2_session_open(
    string $material_id,
    $vms_json = null,
    $metadata = null
) {
    if ($material_id === '') {
        return new WP_Error('vms_v2_invalid_material', 'material_id is required');
    }

    $api_key = vms_v2_get_api_key();
    if ($api_key === '') {
        return new WP_Error('vms_v2_config', __('API Key not set', 'verstka-backend-v2'));
    }

    $secret = vms_v2_get_api_secret();
    if ($secret === '') {
        return new WP_Error('vms_v2_config', __('API Secret not set', 'verstka-backend-v2'));
    }

    $existing_metadata = vms_v2_coerce_json_array($metadata) ?? [];
    $merged_metadata = array_merge(['version' => '2.0'], $existing_metadata);

    $basic_user = vms_v2_get_basic_auth_user();
    $basic_password = vms_v2_get_basic_auth_password();
    if ($basic_user !== '' && $basic_password !== '') {
        $merged_metadata['webhook_basic_auth_user'] = $basic_user;
        $merged_metadata['webhook_basic_auth_password'] = $basic_password;
    }

    $callback_url = vms_v2_get_callback_url();
    $payload = [
        'api_key' => $api_key,
        'callback_url' => $callback_url,
        'material_id' => $material_id,
        'metadata' => $merged_metadata,
    ];

    $vms_json_dict = vms_v2_coerce_json_array($vms_json);
    if ($vms_json_dict !== null) {
        $payload['vms_json'] = $vms_json_dict;
    }

    $signature = vms_v2_sign($material_id, $callback_url, $secret);

    $vms_v2_request_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('vms_v2_', true);
    $vms_v2_user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    $vms_v2_request_uri = str_replace(["\r", "\n"], '', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    $vms_v2_cache_key = vms_v2_session_cache_key($material_id, $payload, $vms_v2_user_id);
    $vms_v2_lock_key = vms_v2_session_lock_key($vms_v2_cache_key);
    $vms_v2_cached_editor_url = vms_v2_session_get_cached_editor_url($vms_v2_cache_key);
    if ($vms_v2_cached_editor_url !== null) {
        error_log(sprintf(
            '[vms_v2_session_open] phase=cache_hit request_id=%s material_id=%s user_id=%d request_uri=%s',
            $vms_v2_request_id,
            $material_id,
            $vms_v2_user_id,
            $vms_v2_request_uri
        ));
        return $vms_v2_cached_editor_url;
    }

    $vms_v2_lock_acquired = vms_v2_session_acquire_lock($vms_v2_lock_key, $vms_v2_request_id);
    if (!$vms_v2_lock_acquired) {
        error_log(sprintf(
            '[vms_v2_session_open] phase=lock_wait request_id=%s material_id=%s user_id=%d request_uri=%s',
            $vms_v2_request_id,
            $material_id,
            $vms_v2_user_id,
            $vms_v2_request_uri
        ));

        $vms_v2_cached_editor_url = vms_v2_session_wait_for_cached_editor_url($vms_v2_cache_key);
        if ($vms_v2_cached_editor_url !== null) {
            error_log(sprintf(
                '[vms_v2_session_open] phase=lock_wait_hit request_id=%s material_id=%s user_id=%d request_uri=%s',
                $vms_v2_request_id,
                $material_id,
                $vms_v2_user_id,
                $vms_v2_request_uri
            ));
            return $vms_v2_cached_editor_url;
        }

        error_log(sprintf(
            '[vms_v2_session_open] phase=lock_wait_miss request_id=%s material_id=%s user_id=%d request_uri=%s',
            $vms_v2_request_id,
            $material_id,
            $vms_v2_user_id,
            $vms_v2_request_uri
        ));
        $vms_v2_lock_acquired = vms_v2_session_acquire_lock($vms_v2_lock_key, $vms_v2_request_id);
    }

    error_log(sprintf(
        '[vms_v2_session_open] phase=start request_id=%s material_id=%s user_id=%d request_uri=%s api_url=%s callback_url=%s',
        $vms_v2_request_id,
        $material_id,
        $vms_v2_user_id,
        $vms_v2_request_uri,
        vms_v2_get_session_open_url(),
        $callback_url
    ));

    $response = vms_v2_http_post_json(
        vms_v2_get_session_open_url(),
        $payload,
        ['X-Verstka-Signature' => $signature],
        vms_v2_get_request_timeout()
    );

    if (is_wp_error($response)) {
        vms_v2_session_release_lock($vms_v2_lock_key);
        error_log(sprintf(
            '[vms_v2_session_open] phase=error request_id=%s material_id=%s user_id=%d wp_error=%s',
            $vms_v2_request_id,
            $material_id,
            $vms_v2_user_id,
            str_replace(["\r", "\n"], ' ', $response->get_error_message())
        ));

        return new WP_Error(
            'vms_v2_api_unreachable',
            sprintf(__('Request to Verstka API failed: %s', 'verstka-backend-v2'), $response->get_error_message())
        );
    }

    $code = $response['http_code'];
    $body = $response['body'];
    error_log(sprintf(
        '[vms_v2_session_open] phase=response request_id=%s material_id=%s user_id=%d http_code=%d body_bytes=%d',
        $vms_v2_request_id,
        $material_id,
        $vms_v2_user_id,
        $code,
        strlen($body)
    ));

    if ($code !== 200) {
        vms_v2_session_release_lock($vms_v2_lock_key);
        $message = sprintf(__('Verstka API HTTP error: %d', 'verstka-backend-v2'), $code);
        if (vms_v2_is_dev_mode()) {
            $message .= ' ' . $body;
        }
        return new WP_Error('vms_v2_api_error', $message, ['status' => $code]);
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['url']) || !is_string($data['url'])) {
        vms_v2_session_release_lock($vms_v2_lock_key);
        return new WP_Error('vms_v2_api_error', __('Unexpected response from Verstka API', 'verstka-backend-v2'));
    }

    vms_v2_session_set_cached_editor_url($vms_v2_cache_key, $data['url']);
    vms_v2_session_release_lock($vms_v2_lock_key);

    return $data['url'];
}


/**
 * @param array<string, mixed> $payload
 */
function vms_v2_session_cache_key(string $material_id, array $payload, int $user_id): string
{
    $encoded_payload = function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload);
    return 'vms_v2_session_open_' . md5($user_id . '|' . $material_id . '|' . (string) $encoded_payload);
}

function vms_v2_session_lock_key(string $cache_key): string
{
    return 'vms_v2_session_open_lock_' . md5($cache_key);
}

function vms_v2_session_get_cached_editor_url(string $cache_key): ?string
{
    $cached_editor_url = get_transient($cache_key);
    if (is_string($cached_editor_url) && $cached_editor_url !== '') {
        return $cached_editor_url;
    }

    return null;
}

function vms_v2_session_set_cached_editor_url(string $cache_key, string $editor_url): void
{
    set_transient($cache_key, $editor_url, VMS_V2_SESSION_OPEN_CACHE_TTL);
}

function vms_v2_session_acquire_lock(string $lock_key, string $request_id): bool
{
    $lock_value = [
        'request_id' => $request_id,
        'expires_at' => time() + VMS_V2_SESSION_OPEN_LOCK_TTL,
    ];

    if (function_exists('add_option') && add_option($lock_key, $lock_value, '', 'no')) {
        return true;
    }

    if (function_exists('get_option') && function_exists('delete_option') && function_exists('add_option')) {
        $existing = get_option($lock_key);
        $expires_at = is_array($existing) ? (int) ($existing['expires_at'] ?? 0) : 0;
        if ($expires_at > 0 && $expires_at < time()) {
            delete_option($lock_key);
            return add_option($lock_key, $lock_value, '', 'no');
        }

        return false;
    }

    if (get_transient($lock_key) !== false) {
        return false;
    }

    return set_transient($lock_key, $request_id, VMS_V2_SESSION_OPEN_LOCK_TTL);
}

function vms_v2_session_release_lock(string $lock_key): void
{
    if (function_exists('delete_option')) {
        delete_option($lock_key);
    }

    delete_transient($lock_key);
}

function vms_v2_session_wait_for_cached_editor_url(string $cache_key): ?string
{
    for ($attempt = 0; $attempt < VMS_V2_SESSION_OPEN_WAIT_ATTEMPTS; $attempt++) {
        usleep(VMS_V2_SESSION_OPEN_WAIT_US);
        $cached_editor_url = vms_v2_session_get_cached_editor_url($cache_key);
        if ($cached_editor_url !== null) {
            return $cached_editor_url;
        }
    }

    return null;
}


/**
 * Build session metadata for a WordPress post editor open request.
 *
 * @return array<string, mixed>
 */
function vms_v2_build_editor_metadata(int $post_id): array
{
    $metadata = [
        'host_name' => (string) parse_url(home_url(), PHP_URL_HOST),
    ];

    $user = wp_get_current_user();
    if (!empty($user->user_email)) {
        $metadata['user_email'] = $user->user_email;
    }

    $fonts_css_url = vms_v2_get_fonts_css_url();
    if ($fonts_css_url !== '') {
        $metadata['fonts.css'] = $fonts_css_url;
    }

    unset($post_id);

    return $metadata;
}

/**
 * Load stored vms_json for a post.
 *
 * @return array<string, mixed>|null
 */
function vms_v2_load_post_vms_json(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!$post || empty($post->post_vms_json)) {
        return null;
    }

    return vms_v2_coerce_json_array($post->post_vms_json);
}