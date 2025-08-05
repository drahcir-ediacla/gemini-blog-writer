<?php
/*
Plugin Name: Gemini Blog Writer
Description: Easily generate engaging blog posts using the Gemini API (Google MakerSuite) and enhance them with AI-generated images from OpenAI's DALL·E. Automatically pull and rewrite or summarize content from RSS feeds for effortless content automation. Seamlessly integrate with MailPoet to instantly notify subscribers about newly published articles.
Version: 1
Author: Richard Alcaide
*/

if (!defined('ABSPATH')) {
    exit;
}

define('GEMINI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GEMINI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GEMINI_PLUGIN_DIR . 'includes/admin-page.php';
require_once GEMINI_PLUGIN_DIR . 'includes/page-generator.php';
require_once GEMINI_PLUGIN_DIR . 'includes/cron-functions.php';
require_once GEMINI_PLUGIN_DIR . 'includes/content-generator.php';
require_once GEMINI_PLUGIN_DIR . 'includes/manual-generator.php';
require_once GEMINI_PLUGIN_DIR . 'includes/dalle-image-generator.php';
require_once GEMINI_PLUGIN_DIR . 'includes/mailpoet-newsletter.php';


function gemini_enqueue_admin_scripts($hook) {
    if (strpos($hook, 'gemini-blog') !== false) {
        wp_enqueue_script('gemini-admin', GEMINI_PLUGIN_URL . 'assets/gemini-blog.js', ['jquery'], '1.0', true);
    }
}
add_action('admin_enqueue_scripts', 'gemini_enqueue_admin_scripts');