<?php

if (!defined('ABSPATH')) {
    exit;
}

function vms_v2_register_rest_routes(): void
{
    add_action('rest_api_init', 'vms_v2_rest_api_init');
}

function vms_v2_rest_api_init(): void
{
    register_rest_route(
        'verstka/v2',
        '/callback',
        [
            'methods' => 'POST',
            'callback' => 'vms_v2_rest_callback',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'verstka/v2',
        '/test',
        [
            'methods' => 'GET',
            'callback' => 'vms_v2_rest_test',
            'permission_callback' => '__return_true',
        ]
    );
}

function vms_v2_rest_callback(WP_REST_Request $request): WP_REST_Response
{
    $payload = $request->get_json_params();
    if (!is_array($payload)) {
        $payload = $request->get_body_params();
    }
    if (!is_array($payload)) {
        $payload = [];
    }

    $signature = trim((string) $request->get_header('X-Verstka-Signature'));

    if (vms_v2_get_api_key() === '') {
        return vms_v2_form_json(0, 'API Key not set');
    }

    return vms_v2_process_callback($payload, $signature);
}

function vms_v2_rest_test(): WP_REST_Response
{
    return rest_ensure_response([
        'status' => 'success',
        'message' => 'Verstka REST API v2 is working',
        'version' => VMS_V2_VERSION,
        'phpVersion' => phpversion(),
        'wordpressVersion' => get_bloginfo('version'),
        'restUrl' => rest_url('verstka/v2/'),
        'timestamp' => current_time('mysql'),
    ]);
}
