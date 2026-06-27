<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('str_contains')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_starts_with($haystack, $needle)
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_ends_with($haystack, $needle)
    {
        if ($needle === '') {
            return true;
        }

        $len = strlen($needle);

        return substr($haystack, -$len) === $needle;
    }
}
