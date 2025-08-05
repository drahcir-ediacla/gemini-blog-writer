<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once GEMINI_PLUGIN_DIR . 'includes/cron-functions.php';

add_action('admin_menu', 'gemini_blog_generator_menu');

function gemini_blog_generator_menu()
{
    add_menu_page('Gemini Blog Generator', 'Gemini Blog Writer', 'manage_options', 'gemini-blog-writer', 'gemini_blog_generator_page');
    add_submenu_page('gemini-blog-writer', 'Generate Post', 'Generate Post', 'manage_options', 'gemini-blog-writer', 'gemini_blog_generator_page');
    add_submenu_page('gemini-blog-writer', 'Gemini Settings', 'Settings', 'manage_options', 'gemini-blog-settings', 'gemini_blog_settings_page');
}

function gemini_blog_settings_page()
{
    if (isset($_POST['gemini_settings_submit'])) {
        update_option('gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
        update_option('gemini_rss_url', esc_url_raw($_POST['gemini_rss_url']));
        update_option('gemini_model', sanitize_text_field($_POST['gemini_model']));
        update_option('gemini_rss_mode', sanitize_text_field($_POST['gemini_rss_mode']));
        update_option('gemini_auto_posting', isset($_POST['gemini_auto_posting']) ? 'yes' : 'no');
        update_option('gemini_auto_post_interval', sanitize_text_field($_POST['gemini_auto_post_interval']));
        update_option('enable_openai_image', isset($_POST['enable_openai_image']) ? 'yes' : 'no');
        update_option('openai_api_key', sanitize_text_field($_POST['openai_api_key']));

        wp_clear_scheduled_hook('gemini_auto_post_event');

        if (get_option('gemini_auto_posting', 'no') === 'yes') {
            $interval = get_option('gemini_auto_post_interval', 'hourly');
            $interval_seconds = 0;

            // Calculate seconds for first run offset
            if ($interval === 'hourly') {
                $interval_seconds = HOUR_IN_SECONDS;
            } elseif ($interval === '4hours') {
                $interval_seconds = 4 * HOUR_IN_SECONDS;
            } elseif ($interval === '8hours') {
                $interval_seconds = 8 * HOUR_IN_SECONDS;
            } elseif ($interval === 'daily') {
                $interval_seconds = DAY_IN_SECONDS;
            } elseif ($interval === 'weekly') {
                $interval_seconds = 7 * DAY_IN_SECONDS;
            }

            // Schedule first event after the chosen interval
            if (!wp_next_scheduled('gemini_auto_post_event')) {
                wp_schedule_event(time() + $interval_seconds, $interval, 'gemini_auto_post_event');
            }
        }


        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $next_scheduled = wp_next_scheduled('gemini_auto_post_event');
    if ($next_scheduled) {
        echo '<p>Gemini auto post next scheduled at: ' . date('Y-m-d H:i:s', $next_scheduled + (get_option('gmt_offset') * HOUR_IN_SECONDS)) . '</p>';
    }


    $api_key = get_option('gemini_api_key', '');
    $rss_url = get_option('gemini_rss_url', '');
    $model = get_option('gemini_model', 'gemini-1.5-flash');
    $rss_mode = get_option('gemini_rss_mode', '3');
    $auto_posting = get_option('gemini_auto_posting', 'no');
    $auto_post_interval = get_option('gemini_auto_post_interval', 'hourly');
    ?>
    <div class="wrap">
        <h1>Gemini Blog Writer Settings</h1>
        <form method="post">
            <label for="gemini_api_key">Gemini API Key:</label><br>
            <input type="text" id="gemini_api_key" name="gemini_api_key" size="80"
                value="<?php echo esc_attr($api_key); ?>"><br><br>

            <label for="enable_openai_image">Enable OpenAI Image Generation:</label><br>
            <label class="switch">
                <input type="checkbox" id="enable_openai_image" name="enable_openai_image" value="yes" <?php checked(get_option('enable_openai_image', 'no'), 'yes'); ?>>
                <span class="slider"></span>
            </label><br><br>

            <div id="openai-key-container"
                style="<?php echo (get_option('enable_openai_image', 'no') === 'yes') ? 'display:block;' : 'display:none;'; ?>">
                <label for="openai_api_key">OpenAI API Key:</label><br>
                <input type="text" id="openai_api_key" name="openai_api_key" size="80"
                    value="<?php echo esc_attr(get_option('openai_api_key', '')); ?>"><br><br>
            </div>

            <label for="gemini_rss_url">Default RSS Feed URL:</label><br>
            <input type="text" id="gemini_rss_url" name="gemini_rss_url" size="80"
                value="<?php echo esc_attr($rss_url); ?>"><br><br>

            <label for="gemini_model">Gemini Model:</label><br>
            <select id="gemini_model" name="gemini_model">
                <option value="gemini-1.5-flash" <?php selected($model, 'gemini-1.5-flash'); ?>>gemini-1.5-flash</option>
                <option value="gemini-2.5-pro" <?php selected($model, 'gemini-2.5-pro'); ?>>gemini-2.5-pro</option>
            </select><br><br>

            <label for="gemini_rss_mode">RSS Rewrite Mode:</label><br>
            <select name="gemini_rss_mode" id="gemini_rss_mode">
                <option value="latest" <?php selected($rss_mode, 'latest'); ?>>Only the latest RSS item</option>
                <option value="3" <?php selected($rss_mode, '3'); ?>>Top 3 recent items</option>
                <option value="5" <?php selected($rss_mode, '5'); ?>>Top 5 recent items</option>
            </select><br><br>

            <label for="gemini_auto_posting">Enable Auto-Posting:</label><br>
            <label class="switch">
                <input type="checkbox" id="gemini_auto_posting" name="gemini_auto_posting" value="yes" <?php checked($auto_posting, 'yes'); ?>>
                <span class="slider"></span>
            </label><br><br>

            <div id="interval-options"
                style="<?php echo ($auto_posting === 'yes') ? 'display:block;' : 'display:none;'; ?>">
                <label for="gemini_auto_post_interval">Auto-Post Interval:</label><br>
                <select id="gemini_auto_post_interval" name="gemini_auto_post_interval">
                    <option value="hourly" <?php selected($auto_post_interval, 'hourly'); ?>>Every 1 Hour</option>
                    <option value="4hours" <?php selected($auto_post_interval, '4hours'); ?>>Every 4 Hours</option>
                    <option value="8hours" <?php selected($auto_post_interval, '8hours'); ?>>Every 8 Hours</option>
                    <option value="daily" <?php selected($auto_post_interval, 'daily'); ?>>Every 1 Day</option>
                    <option value="weekly" <?php selected($auto_post_interval, 'weekly'); ?>>Every 1 Week</option>
                </select>
                <br><br>
            </div>

            <input type="submit" name="gemini_settings_submit" class="button button-primary" value="Save Settings">
        </form>
    </div>

    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 28px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #4caf50;
        }

        input:checked+.slider:before {
            transform: translateX(22px);
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('gemini_auto_posting');
            const intervalOptions = document.getElementById('interval-options');
            function updateIntervalVisibility() {
                intervalOptions.style.display = toggle.checked ? 'block' : 'none';
            }
            toggle.addEventListener('change', updateIntervalVisibility);
            updateIntervalVisibility();
        });
        const openaiToggle = document.getElementById('enable_openai_image');
        const openaiContainer = document.getElementById('openai-key-container');
        function updateOpenAIVisibility() {
            openaiContainer.style.display = openaiToggle.checked ? 'block' : 'none';
        }
        openaiToggle.addEventListener('change', updateOpenAIVisibility);
        updateOpenAIVisibility();
    </script>
    <?php
}
