<?php
/*
Plugin Name: Jobvite
Description: Custom Jobvite Api
Version: 1.0
Author: Alex Nguyen
Text Domain: jobvite
License: GPLv3
*/

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

date_default_timezone_set('America/Los_Angeles');

/**
 * Active Cache by Cron Settings.
 */
function jv_cache_activation()
{
    if (! wp_next_scheduled('jv_cache_cron')) {
        wp_schedule_event(time(), 'hourly', 'jv_cache_cron');
    }
}
register_activation_hook(__FILE__, 'jv_cache_activation');

/**
 * Remove Cache by Cron Settings.
 */
function jv_cache_deactivation()
{
    wp_clear_scheduled_hook('jv_cache_cron');
}
register_deactivation_hook(__FILE__, 'jv_cache_deactivation');
add_action('jv_cache_cron', 'jv_clear_cache');

require_once dirname(__FILE__) . '/inc/class-jobvite.php';
$jv = new Jobvite();

if (! function_exists('jv_clear_cache')) {
    function jv_clear_cache()
    {
        $jv->jv_cron_feed_update();
    }
}
