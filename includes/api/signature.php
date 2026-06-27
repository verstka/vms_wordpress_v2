<?php

if (!defined('ABSPATH')) {
    exit;
}

function vms_v2_sign(string $material_id, string $url, string $secret): string
{
    if ($secret === '') {
        throw new InvalidArgumentException('secret is required to compute a signature');
    }

    return hash_hmac('sha256', $material_id . ':' . $url, $secret);
}

function vms_v2_verify_signature(
    string $material_id,
    string $url,
    string $signature,
    string $secret
): bool {
    if ($signature === '') {
        return false;
    }

    $expected = vms_v2_sign($material_id, $url, $secret);

    return hash_equals($expected, $signature);
}
