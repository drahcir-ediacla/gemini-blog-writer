<?php
if (!defined('ABSPATH')) exit;

require_once GEMINI_PLUGIN_DIR . 'includes/content-generator.php';
require_once GEMINI_PLUGIN_DIR . 'includes/dalle-image-generator.php'; // for gemini_generate_dalle_image_base64()

// 🔁 Register custom intervals for WP Cron
add_filter('cron_schedules', 'gemini_custom_cron_schedules');
function gemini_custom_cron_schedules($schedules) {
    $schedules['4hours'] = ['interval' => 4 * HOUR_IN_SECONDS, 'display' => __('Every 4 Hours')];
    $schedules['8hours'] = ['interval' => 8 * HOUR_IN_SECONDS, 'display' => __('Every 8 Hours')];
    $schedules['3days'] = ['interval' => 3 * DAY_IN_SECONDS, 'display' => __('Every 3 Days')];
    $schedules['weekly'] = ['interval' => 7 * DAY_IN_SECONDS, 'display' => __('Every 1 Week')];
    return $schedules;
}

// 🚀 Cron callback
add_action('gemini_auto_post_event', 'gemini_do_auto_post');

function gemini_do_auto_post() {
    $rss_url = get_option('gemini_rss_url', '');
    if (!$rss_url) {
        error_log("[Gemini Cron] ❌ RSS URL not set.");
        return;
    }

    $content_data = gemini_generate_content_from_rss($rss_url);

    if (!$content_data || empty($content_data['content'])) {
        error_log("[Gemini Cron] ❌ Content generation failed or returned empty.");
        return;
    }

    $post_data = [
        'post_title'   => wp_strip_all_tags($content_data['title']),
        'post_content' => $content_data['content'],
        'post_status'  => 'draft',
        'post_author'  => 1,
        'post_type'    => 'post',
    ];

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        error_log("[Gemini Cron] ❌ Post insert error: " . $post_id->get_error_message());
        return;
    }

    if (!is_wp_error($post_id)) {
        update_post_meta($post_id, '_gemini_generated', true);

        // Now publish it after meta is saved
        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'publish'
        ]);
    }

    error_log("[Gemini Cron] ✅ Post created: ID $post_id");

    // 🖼️ Image generation (if enabled)
    if (get_option('enable_openai_image', 'no') === 'yes') {
        $base64 = gemini_generate_dalle_image_base64($content_data['title']);

        if ($base64) {
            $upload_dir = wp_upload_dir();
            wp_mkdir_p($upload_dir['path']);

            $filename = sanitize_file_name($content_data['title']) . '.jpg';
            $filepath = $upload_dir['path'] . '/' . $filename;

            if (preg_match('/^data:image\/(?:jpeg|jpg|png);base64,/', $base64)) {
                $base64 = preg_replace('/^data:image\/(?:jpeg|jpg|png);base64,/', '', $base64);
            }

            $image_data = base64_decode($base64);
            if ($image_data) {
                $image = imagecreatefromstring($image_data);
                if ($image !== false) {
                    $orig_w = imagesx($image);
                    $orig_h = imagesy($image);
                    $max_w = 1024;

                    if ($orig_w > $max_w) {
                        $ratio = $max_w / $orig_w;
                        $new_h = intval($orig_h * $ratio);
                        $resized = imagecreatetruecolor($max_w, $new_h);
                        imagecopyresampled($resized, $image, 0, 0, 0, 0, $max_w, $new_h, $orig_w, $orig_h);
                        imagejpeg($resized, $filepath, 85);
                        imagedestroy($resized);
                    } else {
                        imagejpeg($image, $filepath, 85);
                    }
                    imagedestroy($image);
                } else {
                    file_put_contents($filepath, $image_data);
                }

                $attachment_id = wp_insert_attachment([
                    'post_mime_type' => 'image/jpeg',
                    'post_title'     => $content_data['title'],
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ], $filepath, $post_id);

                if (!is_wp_error($attachment_id)) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $attach_data = wp_generate_attachment_metadata($attachment_id, $filepath);
                    wp_update_attachment_metadata($attachment_id, $attach_data);

                    update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Illustration for: ' . $content_data['title']);
                    wp_update_post([
                        'ID' => $attachment_id,
                        'post_excerpt' => 'Illustration for: ' . $content_data['title'],
                        'post_title' => 'Illustration for: ' . $content_data['title']
                    ]);

                    set_post_thumbnail($post_id, $attachment_id);
                    error_log("[Gemini Cron] 🖼️ Featured image added to post ID $post_id");
                } else {
                    error_log("[Gemini Cron] ⚠️ Failed to attach image: " . $attachment_id->get_error_message());
                }
            }
        } else {
            error_log("[Gemini Cron] ⚠️ OpenAI image generation failed.");
        }
    }
}
