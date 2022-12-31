<?php

/**
 * @package WordPress
 */

ignore_user_abort( true );

if ( function_exists( 'fastcgi_finish_request' ) && version_compare( phpversion(), '7.0.16', '>=' ) ) {
    if ( ! headers_sent() ) {
        header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
        header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
    }

    fastcgi_finish_request();
}

if (!defined('ABSPATH')) {
    /** Set up WordPress environment */
    $path = preg_replace('/wp-content.*$/', '', __DIR__);
    require_once $path . 'wp-load.php';
}

if (strpos(@ini_get('disable_functions'), 'set_time_limit') === false) {
    set_time_limit(0);
}

function _get_cron_lock()
{
    global $wpdb;

    $value = 0;
    if (wp_using_ext_object_cache()) {
        /*
         * Skip local cache and force re-fetch of doing_cron transient
         * in case another process updated the cache.
         */
        $value = wp_cache_get('wpnotif_cron', 'transient', true);
    } else {
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", '_wpnotif_cron'));
        if (is_object($row)) {
            $value = $row->option_value;
        }
    }

    return $value;
}

$gmt_time = microtime(true);

// The cron lock: a unix timestamp from when the cron was spawned.
$doing_cron_transient = get_transient('wpnotif_cron');


// Called from external script/job. Try setting a lock.
if ($doing_cron_transient && ($doing_cron_transient + WP_CRON_LOCK_TIMEOUT > $gmt_time)) {
    return;
}
$doing_wp_cron = sprintf('%.22F', microtime(true));
$doing_cron_transient = $doing_wp_cron;
set_transient('wpnotif_cron', $doing_wp_cron);


/*
 * The cron lock (a unix timestamp set when the cron was spawned),
 * must match $doing_wp_cron (the "key").
 */
if ($doing_cron_transient !== $doing_wp_cron) {
    return;
}


WPNotif_NewsLetter_Handler::run_wpn_cron();


if (_get_cron_lock() === $doing_wp_cron) {
    delete_transient('wpnotif_cron');
}

die();