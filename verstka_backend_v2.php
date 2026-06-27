<?php
/*
Plugin Name: Verstka Backend v2
Plugin URI: https://github.com/verstka/vms_wordpress
Description: Verstka visual editor integration for WordPress (API v2).
Version: 2.0.0
Author: Verstka
Author URI: https://verstka.io
Text Domain: verstka-backend-v2
Domain Path: /languages
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) {
    exit;
}

define('VMS_V2_PLUGIN_FILE', __FILE__);
define('VMS_V2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VMS_V2_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VMS_V2_VERSION', '2.0.0');

require_once VMS_V2_PLUGIN_DIR . 'includes/bootstrap.php';

register_activation_hook(__FILE__, 'vms_v2_activate');
register_deactivation_hook(__FILE__, 'vms_v2_deactivate');

add_action('plugins_loaded', 'vms_v2_init');
