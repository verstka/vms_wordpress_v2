<?php

if (!defined('ABSPATH')) {
    exit;
}

function vms_v2_build_authorized_content_url(
    string $content_url,
    string $api_key,
    string $material_id
): string {
    if ($content_url === '') {
        throw new InvalidArgumentException('content_url must be a non-empty string');
    }

    $parts = parse_url($content_url);
    if ($parts === false) {
        throw new InvalidArgumentException('content_url must be a valid URL');
    }

    $query = [];
    if (isset($parts['query']) && $parts['query'] !== '') {
        parse_str($parts['query'], $query);
    }

    if ($api_key !== '') {
        $query['api_key'] = $api_key;
    }
    if ($material_id !== '') {
        $query['material_id'] = $material_id;
    }

    $parts['query'] = http_build_query($query);

    return vms_v2_build_url_from_parts($parts);
}

/**
 * @param array<string, mixed> $parts
 */
function vms_v2_build_url_from_parts(array $parts): string
{
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $user = $parts['user'] ?? '';
    $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
    $pass = ($user !== '' || $pass !== '') ? $pass . '@' : '';
    $path = $parts['path'] ?? '';
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
}
