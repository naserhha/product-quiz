<?php
/**
 * Uninstall file for Perfume Quiz plugin
 * 
 * @package Perfume_Quiz
 * @author  پرشین دیجیتال | Persian Digital
 * @link    https://persiandigital.com
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete the plugin options from the database
delete_option('perfume_quiz_settings');

// Clear any cached data that the plugin might have set
wp_cache_flush(); 