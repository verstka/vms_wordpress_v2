<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST JSON request via WordPress HTTP API.
 *
 * @param array<string, mixed> $body
 * @param array<string, string> $headers
 * @return array{http_code: int, body: string}|WP_Error
 */
function vms_v2_http_post_json(
    string $url,
    array $body,
    array $headers = [],
    int $timeout = 60
) {
    $response = wp_remote_post($url, [
        'timeout' => $timeout,
        'headers' => array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers),
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    return [
        'http_code' => (int) wp_remote_retrieve_response_code($response),
        'body' => (string) wp_remote_retrieve_body($response),
    ];
}

/**
 * Stream download to a file with size limit (uses cURL when available).
 *
 * @param array<string, string> $headers
 * @return true|WP_Error
 */
function vms_v2_http_download(
    string $url,
    string $dest_path,
    array $headers = [],
    int $max_size = 104857600,
    int $timeout = 120
) {
    if (function_exists('curl_init')) {
        return vms_v2_curl_download($url, $dest_path, $headers, $max_size, $timeout);
    }

    $response = wp_remote_get($url, [
        'timeout' => $timeout,
        'headers' => $headers,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return new WP_Error(
            'vms_v2_download_failed',
            sprintf('Failed to download content: HTTP %d', $code)
        );
    }

    $body = (string) wp_remote_retrieve_body($response);
    if (strlen($body) > $max_size) {
        return new WP_Error(
            'vms_v2_content_too_large',
            sprintf('Content file too large: %d bytes (max: %d)', strlen($body), $max_size)
        );
    }

    if (file_put_contents($dest_path, $body) === false) {
        return new WP_Error('vms_v2_write_failed', 'Failed to write downloaded content');
    }

    return true;
}

/**
 * @param array<string, string> $headers
 * @return true|WP_Error
 */
function vms_v2_curl_download(
    string $url,
    string $dest_path,
    array $headers = [],
    int $max_size = 104857600,
    int $timeout = 120
) {
    $handle = fopen($dest_path, 'wb');
    if ($handle === false) {
        return new WP_Error('vms_v2_write_failed', 'Failed to open destination file');
    }

    $downloaded = 0;
    $ch = curl_init($url);
    if ($ch === false) {
        fclose($handle);
        return new WP_Error('vms_v2_curl_init', 'Failed to initialize cURL');
    }

    $curl_headers = [];
    foreach ($headers as $name => $value) {
        $curl_headers[] = $name . ': ' . $value;
    }

    curl_setopt_array($ch, [
        CURLOPT_FILE => $handle,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $curl_headers,
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => static function (
            $resource,
            float $download_total,
            float $downloaded_now,
            float $upload_total,
            float $uploaded_now
        ) use (&$downloaded, $max_size) {
            unset($resource, $download_total, $upload_total, $uploaded_now);
            $downloaded = (int) $downloaded_now;
            if ($downloaded > $max_size) {
                return 1;
            }
            return 0;
        },
    ]);

    $success = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    fclose($handle);

    if (!$success || $downloaded > $max_size) {
        @unlink($dest_path);
        if ($downloaded > $max_size) {
            return new WP_Error(
                'vms_v2_content_too_large',
                sprintf('Content file too large (max: %d)', $max_size)
            );
        }
        return new WP_Error(
            'vms_v2_download_failed',
            $curl_error !== '' ? $curl_error : 'Download failed'
        );
    }

    if ($http_code === 403) {
        @unlink($dest_path);
        return new WP_Error('vms_v2_download_forbidden', 'Access denied: invalid API key or signature');
    }
    if ($http_code === 404) {
        @unlink($dest_path);
        return new WP_Error('vms_v2_download_not_found', 'Content not found');
    }
    if ($http_code < 200 || $http_code >= 300) {
        @unlink($dest_path);
        return new WP_Error(
            'vms_v2_download_failed',
            sprintf('Failed to download content: HTTP %d', $http_code)
        );
    }

    return true;
}
