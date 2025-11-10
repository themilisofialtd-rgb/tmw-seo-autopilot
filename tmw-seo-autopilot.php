<?php
/**
 * Plugin Name: TMW SEO Autopilot
 * Description: Auto-fills RankMath SEO + inserts intro/bio/FAQ for Model CPT. Admin, Bulk, CLI. Template-first; optional AI provider.
 * Version: 0.8.0
 * Author: The Milisofia Ltd
 * License: GPLv2 or later
 */
if (!defined('ABSPATH')) exit;

define('TMW_SEO_PATH', plugin_dir_path(__FILE__));
define('TMW_SEO_URL', plugin_dir_url(__FILE__));
define('TMW_SEO_TAG', '[TMW-SEO]');

require_once TMW_SEO_PATH . 'includes/class-tmw-seo.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-admin.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-cli.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-rankmath.php';
require_once TMW_SEO_PATH . 'includes/providers/class-provider-template.php';
require_once TMW_SEO_PATH . 'includes/providers/class-provider-openai.php';

add_action('plugins_loaded', function () {
    if (is_admin()) {
        \TMW_SEO\Admin::boot();
    }
    \TMW_SEO\RankMath::boot();
});

register_activation_hook(__FILE__, function () {
    error_log(TMW_SEO_TAG . ' activated v0.8.0');
});
