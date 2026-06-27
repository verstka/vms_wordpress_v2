<?php

if (!defined('ABSPATH')) {
    exit;
}

const VMS_V2_VMS_FONTS_CSS = 'vms_fonts.css';
const VMS_V2_VMS_FONTS_JSON = 'vms_fonts.json';

/**
 * Dispatch material or fonts callback.
 *
 * @param array<string, mixed> $payload
 */
function vms_v2_process_callback(array $payload, string $signature): WP_REST_Response
{
    if (vms_v2_is_fonts_callback($payload)) {
        return vms_v2_process_fonts_callback($payload, $signature);
    }

    return vms_v2_process_material_callback($payload, $signature);
}

/**
 * @param array<string, mixed> $callback_data
 */
function vms_v2_process_material_callback(array $callback_data, string $signature): WP_REST_Response
{
    $verify = vms_v2_verify_callback_signature($callback_data, $signature);
    if (is_wp_error($verify)) {
        return vms_v2_callback_error_response($verify);
    }

    $material_id = (string) ($callback_data['material_id'] ?? '');
    if ($material_id === '') {
        return vms_v2_form_json(0, 'material_id is required');
    }

    $content_url = (string) ($callback_data['content_url'] ?? '');
    $metadata = is_array($callback_data['metadata'] ?? null) ? $callback_data['metadata'] : [];

    $pre_save = vms_v2_content_pre_save($material_id, $metadata, $content_url);
    if ($pre_save !== true) {
        return vms_v2_form_json(0, is_string($pre_save) ? $pre_save : 'Operation rejected');
    }

    $temp_dir = null;
    try {
        $vms_json_dict = null;
        $vms_html = null;

        if ($content_url !== '') {
            $extracted = vms_v2_download_material_zip($content_url, $material_id, $signature);
            if (is_wp_error($extracted)) {
                return vms_v2_callback_error_response($extracted);
            }

            $temp_dir = $extracted['temp_dir'];
            $vms_json_dict = vms_v2_parse_vms_json($extracted['vms_json']);
            $vms_html = $extracted['vms_html'];

            foreach ($extracted['media'] as $filename => $temp_path) {
                try {
                    $public_url = vms_v2_save_media($filename, $temp_path, $material_id);
                } catch (Throwable $exception) {
                    return vms_v2_form_json(0, $exception->getMessage());
                }

                $vms_html = vms_v2_apply_media_url_patches($filename, $public_url, $vms_html, $vms_json_dict);
            }
        }

        $saved = vms_v2_content_finalize($material_id, $metadata, $vms_json_dict, $vms_html);
        if (is_wp_error($saved)) {
            return vms_v2_form_json(0, $saved->get_error_message());
        }

        $data = [];
        if ($saved['vms_json'] !== null) {
            $data['vms_json'] = $saved['vms_json'];
        }
        if (vms_v2_is_dev_mode()) {
            $data['debug_info'] = [
                'callback_data' => $callback_data,
                'metadata' => $metadata,
            ];
        }

        return vms_v2_form_json(1, 'Saved successfully', $data);
    } finally {
        vms_v2_cleanup_temp_dir($temp_dir);
    }
}

/**
 * @param array{font_files: array<string, string>, vms_fonts_json_path: string|null, vms_fonts_css_path: string|null, temp_dir: string} $extracted
 * @return string|null
 */
function vms_v2_validate_fonts_zip_download(string $zip_path, array $extracted): ?string
{
    if (!is_file($zip_path) || (int) filesize($zip_path) <= 0) {
        return 'Fonts ZIP download is empty';
    }

    $has_css = !empty($extracted['vms_fonts_css_path']) && is_file($extracted['vms_fonts_css_path']);
    $has_json = !empty($extracted['vms_fonts_json_path']) && is_file($extracted['vms_fonts_json_path']);
    $has_fonts = !empty($extracted['font_files']);

    if (!$has_css && !$has_json && !$has_fonts) {
        return 'Fonts ZIP is empty or invalid';
    }

    return null;
}

/**
 * @param array<string, mixed> $callback_data
 */
function vms_v2_process_fonts_callback(array $callback_data, string $signature): WP_REST_Response
{
    $verify = vms_v2_verify_callback_signature($callback_data, $signature);
    if (is_wp_error($verify)) {
        return vms_v2_callback_error_response($verify);
    }

    $material_id = (string) ($callback_data['material_id'] ?? '');
    $content_url = (string) ($callback_data['content_url'] ?? '');
    $metadata = is_array($callback_data['metadata'] ?? null) ? $callback_data['metadata'] : [];
    $fonts_payload = is_array($callback_data['fonts'] ?? null) ? $callback_data['fonts'] : [];

    if ($content_url === '') {
        return vms_v2_form_json(0, 'content_url is required for fonts callback');
    }

    $pre_save = vms_v2_fonts_pre_save($material_id, $metadata, $content_url, $fonts_payload);
    if ($pre_save !== true) {
        return vms_v2_form_json(0, is_string($pre_save) ? $pre_save : 'Operation rejected', [
            'fonts' => $fonts_payload,
        ]);
    }

    $temp_dir = null;
    try {
        $extracted = vms_v2_download_fonts_zip($content_url, $material_id, $signature);
        if (is_wp_error($extracted)) {
            return vms_v2_callback_error_response($extracted);
        }

        $zip_path = trailingslashit($extracted['temp_dir']) . 'fonts.zip';
        $zip_error = vms_v2_validate_fonts_zip_download($zip_path, $extracted);
        if ($zip_error !== null) {
            return vms_v2_form_json(0, $zip_error, ['fonts' => $fonts_payload]);
        }

        $temp_dir = $extracted['temp_dir'];
        $saved_font_urls = [];

        foreach ($extracted['font_files'] as $basename => $temp_path) {
            try {
                $saved_font_urls[$basename] = vms_v2_save_font_file($basename, $temp_path, $material_id);
            } catch (Throwable $exception) {
                return vms_v2_form_json(0, $exception->getMessage(), ['fonts' => $fonts_payload]);
            }
        }

        $css_url = vms_v2_persist_fonts_css(
            $extracted['vms_fonts_css_path'],
            $saved_font_urls,
            $material_id
        );
        vms_v2_fill_font_client_urls($fonts_payload, $saved_font_urls, $css_url);
        vms_v2_persist_fonts_json(
            $extracted['vms_fonts_json_path'],
            $saved_font_urls,
            $css_url,
            $material_id
        );

        $finalize = vms_v2_fonts_finalize($material_id, $metadata, $fonts_payload, $css_url);
        if (is_wp_error($finalize)) {
            return vms_v2_form_json(0, $finalize->get_error_message(), ['fonts' => $fonts_payload]);
        }

        return vms_v2_form_json(1, 'Fonts saved successfully', [
            'fonts' => $fonts_payload,
        ]);
    } finally {
        vms_v2_cleanup_temp_dir($temp_dir);
    }
}

/**
 * @param array<string, mixed> $callback_data
 * @return true|WP_Error
 */
function vms_v2_verify_callback_signature(array $callback_data, string $signature)
{
    $content_url = (string) ($callback_data['content_url'] ?? '');
    $material_id = (string) ($callback_data['material_id'] ?? '');
    $secret = vms_v2_get_api_secret();

    if ($secret === '') {
        return new WP_Error('vms_v2_config', 'Secret not set');
    }

    if (!vms_v2_verify_signature($material_id, $content_url, $signature, $secret)) {
        $message = 'Invalid signature';
        if (vms_v2_is_dev_mode()) {
            $message .= ' ' . var_export($signature, true) . ' for material_id=' . var_export($material_id, true);
        }
        return new WP_Error('vms_v2_invalid_signature', $message);
    }

    return true;
}

/**
 * @return true|string
 */
function vms_v2_validate_callback_user_email(array $metadata)
{
    if (!vms_v2_should_verify_callback_user_email()) {
        return true;
    }

    $email = trim((string) ($metadata['user_email'] ?? ''));
    if ($email === '') {
        return true;
    }

    $user = get_user_by('email', $email);
    if (!$user || !user_can($user, 'edit_posts')) {
        return 'user not allowed';
    }

    return true;
}

/**
 * @return true|string
 */
function vms_v2_content_pre_save(string $material_id, array $metadata, string $content_url)
{
    unset($content_url);

    $post = get_post((int) $material_id);
    if (!$post) {
        return 'unknown material';
    }

    return vms_v2_validate_callback_user_email($metadata);
}

/**
 * @param array<string, mixed> $fonts_payload
 * @return true|string
 */
function vms_v2_fonts_pre_save(
    string $material_id,
    array $metadata,
    string $content_url,
    array $fonts_payload
) {
    unset($material_id, $content_url, $fonts_payload);

    return vms_v2_validate_callback_user_email($metadata);
}

/**
 * @param array<string, mixed>|null $vms_json_dict
 * @return array{vms_json: array<string, mixed>|null}|WP_Error
 */
function vms_v2_content_finalize(
    string $material_id,
    array $metadata,
    ?array $vms_json_dict,
    ?string $vms_html
) {
    unset($metadata);

    global $wpdb;

    $post_id = (int) $material_id;
    $post = get_post($post_id);
    if (!$post) {
        return new WP_Error('vms_v2_unknown_material', 'unknown material');
    }

    $vms_json_string = null;
    if ($vms_json_dict !== null) {
        $encoded = wp_json_encode($vms_json_dict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return new WP_Error('vms_v2_json_encode', 'Failed to encode vms_json');
        }
        $vms_json_string = $encoded;
    }

    $updated = $wpdb->update(
        $wpdb->posts,
        [
            'post_isvms' => 1,
            'post_vms_content' => $vms_html,
            'post_vms_json' => $vms_json_string,
        ],
        ['ID' => $post_id]
    );

    if ($updated === false) {
        return new WP_Error('vms_v2_db_update', 'Failed to update post');
    }

    clean_post_cache($post_id);

    return ['vms_json' => $vms_json_dict];
}

/**
 * @param array<string, mixed> $fonts_payload
 * @return true|WP_Error
 */
function vms_v2_fonts_finalize(
    string $material_id,
    array $metadata,
    array $fonts_payload,
    ?string $css_url
) {
    unset($material_id, $metadata, $fonts_payload);

    if ($css_url !== null && $css_url !== '') {
        update_option('vms_v2_fonts_css_url', $css_url, false);
    }

    $fonts_dir = vms_v2_get_fonts_dir();
    $src = trailingslashit($fonts_dir) . VMS_V2_VMS_FONTS_CSS;
    $dst = trailingslashit($fonts_dir) . 'fonts.css';
    if (is_file($src)) {
        copy($src, $dst);
    }

    return true;
}

/**
 * @return array{media: array<string, string>, vms_json: string|null, vms_html: string|null, temp_dir: string}|WP_Error
 */
function vms_v2_download_material_zip(string $content_url, string $material_id, string $signature)
{
    $temp_dir = vms_v2_make_temp_dir('verstka_content');
    $zip_path = $temp_dir . '/content.zip';
    $authorized_url = vms_v2_build_authorized_content_url(
        $content_url,
        vms_v2_get_api_key(),
        $material_id
    );

    $download = vms_v2_http_download(
        $authorized_url,
        $zip_path,
        ['X-Verstka-Signature' => $signature],
        vms_v2_get_max_content_size(),
        vms_v2_get_download_timeout()
    );

    if (is_wp_error($download)) {
        vms_v2_cleanup_temp_dir($temp_dir);
        return $download;
    }

    $extracted = vms_v2_extract_content_zip($zip_path, $temp_dir);

    return [
        'media' => $extracted['media'],
        'vms_json' => $extracted['vms_json'],
        'vms_html' => $extracted['vms_html'],
        'temp_dir' => $extracted['temp_dir'],
    ];
}

/**
 * @return array{font_files: array<string, string>, vms_fonts_json_path: string|null, vms_fonts_css_path: string|null, temp_dir: string}|WP_Error
 */
function vms_v2_download_fonts_zip(string $content_url, string $material_id, string $signature)
{
    $temp_dir = vms_v2_make_temp_dir('verstka_fonts');
    $zip_path = $temp_dir . '/fonts.zip';
    $authorized_url = vms_v2_build_authorized_content_url(
        $content_url,
        vms_v2_get_api_key(),
        $material_id
    );

    $download = vms_v2_http_download(
        $authorized_url,
        $zip_path,
        ['X-Verstka-Signature' => $signature],
        vms_v2_get_max_content_size(),
        vms_v2_get_download_timeout()
    );

    if (is_wp_error($download)) {
        vms_v2_cleanup_temp_dir($temp_dir);
        return $download;
    }

    return vms_v2_extract_fonts_zip($zip_path, $temp_dir);
}

function vms_v2_parse_vms_json(?string $raw): ?array
{
    if ($raw === null || $raw === '') {
        return null;
    }

    $parsed = json_decode($raw, true);
    return is_array($parsed) ? $parsed : null;
}

/**
 * @param array<string, mixed>|null $vms_json_dict
 */
function vms_v2_apply_media_url_patches(
    string $filename,
    string $public_url,
    ?string $vms_html,
    ?array &$vms_json_dict
): ?string {
    $updated_html = $vms_html;
    if ($updated_html !== null) {
        $dummy = 'dummy-' . $filename;
        if (str_contains($updated_html, $dummy)) {
            $updated_html = str_replace($dummy, $public_url, $updated_html);
        }
    }

    if (
        $vms_json_dict !== null
        && isset($vms_json_dict['assets'])
        && is_array($vms_json_dict['assets'])
        && isset($vms_json_dict['assets'][$filename])
        && is_array($vms_json_dict['assets'][$filename])
    ) {
        $vms_json_dict['assets'][$filename]['clientUrl'] = $public_url;
    }

    return $updated_html;
}

/**
 * @param array<string, string> $saved_files
 */
function vms_v2_patch_css_urls(string $css_text, array $saved_files): string
{
    foreach ($saved_files as $file_id => $public_url) {
        $css_text = str_replace('dummy-' . $file_id, $public_url, $css_text);
    }

    return $css_text;
}

/**
 * @param array<string, mixed> $fonts
 * @param array<string, string> $saved_files
 */
function vms_v2_fill_font_client_urls(array &$fonts, array $saved_files, ?string $css_url): void
{
    if (isset($fonts['css']) && is_array($fonts['css']) && $css_url !== null) {
        $fonts['css']['clientUrl'] = $css_url;
    }

    foreach ($fonts['list'] ?? [] as &$family_entry) {
        if (!is_array($family_entry)) {
            continue;
        }
        foreach ($family_entry['variants'] ?? [] as &$variant) {
            if (!is_array($variant)) {
                continue;
            }
            $files = $variant['files'] ?? [];
            if (!is_array($files)) {
                continue;
            }
            foreach ($files as &$file_info) {
                if (!is_array($file_info)) {
                    continue;
                }
                $file_id = $file_info['id'] ?? null;
                if (is_string($file_id) && isset($saved_files[$file_id])) {
                    $file_info['clientUrl'] = $saved_files[$file_id];
                }
            }
            unset($file_info);
        }
        unset($variant);
    }
    unset($family_entry);
}

/**
 * @param array<string, string> $saved_font_urls
 */
function vms_v2_persist_fonts_css(
    ?string $css_path,
    array $saved_font_urls,
    string $material_id
): ?string {
    if ($css_path === null || !is_file($css_path)) {
        return null;
    }

    vms_v2_rewrite_css_in_place($css_path, $saved_font_urls);

    return vms_v2_save_fonts_manifest(VMS_V2_VMS_FONTS_CSS, $css_path, $material_id);
}

/**
 * @param array<string, string> $saved_font_urls
 */
function vms_v2_persist_fonts_json(
    ?string $json_path,
    array $saved_font_urls,
    ?string $css_url,
    string $material_id
): ?string {
    if ($json_path === null || !is_file($json_path)) {
        return null;
    }

    vms_v2_rewrite_fonts_json_in_place($json_path, $saved_font_urls, $css_url);

    return vms_v2_save_fonts_manifest(VMS_V2_VMS_FONTS_JSON, $json_path, $material_id);
}

/**
 * @param array<string, string> $saved_font_urls
 */
function vms_v2_rewrite_css_in_place(string $css_path, array $saved_font_urls): void
{
    $css_text = file_get_contents($css_path);
    if ($css_text === false) {
        return;
    }

    $patched = vms_v2_patch_css_urls($css_text, $saved_font_urls);
    if ($patched !== $css_text) {
        file_put_contents($css_path, $patched);
    }
}

/**
 * @param array<string, string> $saved_font_urls
 */
function vms_v2_rewrite_fonts_json_in_place(
    string $json_path,
    array $saved_font_urls,
    ?string $css_url
): void {
    $raw = file_get_contents($json_path);
    if ($raw === false) {
        return;
    }

    $fonts = json_decode($raw, true);
    if (!is_array($fonts)) {
        return;
    }

    vms_v2_fill_font_client_urls($fonts, $saved_font_urls, $css_url);
    file_put_contents($json_path, wp_json_encode($fonts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function vms_v2_callback_error_response(WP_Error $error): WP_REST_Response
{
    $code = $error->get_error_code();
    $status = 500;

    if ($code === 'vms_v2_invalid_signature') {
        $status = 400;
    } elseif ($code === 'vms_v2_download_forbidden') {
        $status = 403;
    } elseif ($code === 'vms_v2_download_not_found') {
        $status = 404;
    } elseif (in_array($code, ['vms_v2_content_too_large', 'vms_v2_config'], true)) {
        $status = 400;
    }

    if (vms_v2_is_dev_mode() && in_array($code, ['vms_v2_invalid_signature', 'vms_v2_config'], true)) {
        return vms_v2_form_json(0, $error->get_error_message());
    }

    return new WP_REST_Response([
        'error' => $code,
        'code' => $code,
        'message' => $error->get_error_message(),
    ], $status);
}
