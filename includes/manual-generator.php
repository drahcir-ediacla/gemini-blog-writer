<?php
if (!defined('ABSPATH')) {
    exit;
}

// AJAX action for generating content
add_action('wp_ajax_gemini_generate_content', 'gemini_generate_content_callback');

function gemini_generate_content_callback()
{
    $api_key = sanitize_text_field($_POST['apiKey']);
    $mode = sanitize_text_field($_POST['mode']);
    $rss_url = esc_url_raw($_POST['rssUrl'] ?? '');
    $prompt = '';
    $debug_log = __DIR__ . '/gemini-debug.log';

    $formatting_instructions = "Write a complete blog post in HTML format. 
Use <h4> for headings, <p> for paragraphs, <strong> for emphasis, and lists where appropriate. 
At the end of each summarized article or section, include a line like: 
<p><strong>Source:</strong> <a href=\"[URL]\">Original Article</a></p>.
Do not include <html>, <title>, <head>, or <body> tags. 
Do not wrap the HTML in code blocks — return raw HTML only.\n\n";


    if ($mode === 'rss' && $rss_url) {
        include_once ABSPATH . WPINC . '/feed.php';
        $rss = fetch_feed($rss_url);

        if (!is_wp_error($rss)) {
            $rss_mode = get_option('gemini_rss_mode', '3');
            $items_count = $rss_mode === 'latest' ? 1 : intval($rss_mode);
            $rss_items = $rss->get_items(0, $rss->get_item_quantity($items_count));
            $rss_summary = $formatting_instructions . "Summarize or rewrite the following articles:\n\n";

            foreach ($rss_items as $item) {
                $rss_summary .= "Title: " . $item->get_title() . "\n";
                $rss_summary .= "Content: " . strip_tags($item->get_description()) . "\n\n";
                $rss_summary .= "Source URL: " . $item->get_link() . "\n\n";
            }

            $prompt = $rss_summary;
        } else {
            echo json_encode(['error' => 'Failed to fetch RSS feed.']);
            wp_die();
        }
    } else {
        $user_prompt = sanitize_text_field($_POST['prompt']);
        $prompt = $formatting_instructions . $user_prompt;
    }

    $model = get_option('gemini_model', 'gemini-1.5-flash');
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

    $response = wp_remote_post("{$endpoint}?key={$api_key}", [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]),
        'method' => 'POST',
        'timeout' => 60
    ]);

    if (is_wp_error($response)) {
        file_put_contents($debug_log, "API error: " . $response->get_error_message() . "\n", FILE_APPEND);
        echo json_encode(['error' => 'API request failed.']);
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        file_put_contents($debug_log, print_r($data, true), FILE_APPEND);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = preg_replace(['/^```html\s*/i', '/```$/'], '', $data['candidates'][0]['content']['parts'][0]['text']);

            if (preg_match('/<h2>(.*?)<\/h2>/i', $text, $matches)) {
                $title = wp_strip_all_tags($matches[1]);
                $content = preg_replace('/<h2>.*?<\/h2>/i', '', $text, 1);
            } else {
                $lines = explode("\n", $text);
                $title = wp_strip_all_tags(trim(array_shift($lines)));
                $content = implode("\n", $lines);
            }

            echo json_encode(['title' => $title, 'content' => $content]);
        } else {
            echo json_encode(['error' => 'No text generated.', 'raw' => $data]);
        }
    }

    wp_die();
}

// Handles post publishing
add_action('admin_post_gemini_publish_post', 'gemini_publish_post_handler');
add_action('wp_ajax_gemini_publish_post', 'gemini_publish_post_handler');

function gemini_publish_post_handler()
{
    $debug_log = __DIR__ . '/gemini-debug.log';
    file_put_contents($debug_log, "Publish handler started\n", FILE_APPEND);

    if (!isset($_POST['gemini_nonce_field']) || !wp_verify_nonce($_POST['gemini_nonce_field'], 'gemini_publish_post_nonce')) {
        wp_die('Nonce check failed.');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized.');
    }

    $title = sanitize_text_field($_POST['title']);
    $content = wp_kses_post($_POST['content']);
    $category = intval($_POST['category']);
    $tags = sanitize_text_field($_POST['tags']);
    $base64_image = $_POST['featured_image_base64'] ?? '';

    $post_data = [
        'post_title' => $title ?: wp_strip_all_tags(mb_substr($content, 0, 50)),
        'post_content' => $content,
        'post_status' => 'draft', // <- initially create as draft
        'post_author' => get_current_user_id(),
        'post_type' => 'post',
        'post_category' => $category ? [$category] : []
    ];

    $post_id = wp_insert_post($post_data);

    if (!is_wp_error($post_id)) {
        update_post_meta($post_id, '_gemini_generated', true);

        // Now publish it after meta is saved
        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'publish'
        ]);
    }

    if (is_wp_error($post_id)) {
        file_put_contents($debug_log, "Post insert failed: " . $post_id->get_error_message() . "\n", FILE_APPEND);
        wp_die('Failed to insert post.');
    }

    if ($tags) {
        wp_set_post_tags($post_id, explode(',', $tags));
    }

    if ($base64_image && $post_id) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // If base64 includes data:image/png;base64,... strip prefix
        if (preg_match('/^data:image\/(?:jpeg|jpg|png);base64,/', $base64_image)) {
            $base64_image = preg_replace('/^data:image\/(?:jpeg|jpg|png);base64,/', '', $base64_image);
        }

        $image_data = base64_decode($base64_image);
        if ($image_data) {
            $upload_dir = wp_upload_dir();
            wp_mkdir_p($upload_dir['path']);

            $filename = 'featured-' . time() . '.jpg';
            $file_path = trailingslashit($upload_dir['path']) . $filename;

            try {
                $image = imagecreatefromstring($image_data);
                if ($image !== false) {
                    // Resize if width > 1024
                    $orig_w = imagesx($image);
                    $orig_h = imagesy($image);
                    $max_w = 1024;

                    if ($orig_w > $max_w) {
                        $ratio = $max_w / $orig_w;
                        $new_h = intval($orig_h * $ratio);
                        $resized = imagecreatetruecolor($max_w, $new_h);
                        imagecopyresampled($resized, $image, 0, 0, 0, 0, $max_w, $new_h, $orig_w, $orig_h);
                        imagejpeg($resized, $file_path, 85);
                        imagedestroy($resized);
                    } else {
                        imagejpeg($image, $file_path, 85);
                    }

                    imagedestroy($image);
                } else {
                    file_put_contents($file_path, $image_data);
                    file_put_contents($debug_log, "Fallback: raw image saved.\n", FILE_APPEND);
                }

                $filetype = wp_check_filetype($filename, null);
                $attachment = [
                    'post_mime_type' => $filetype['type'],
                    'post_title' => sanitize_file_name($filename),
                    'post_content' => '',
                    'post_status' => 'inherit'
                ];

                $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
                if (!is_wp_error($attach_id)) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);

                    $alt_text = 'Illustration for blog post: ' . $title;
                    update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
                    wp_update_post([
                        'ID' => $attach_id,
                        'post_excerpt' => $alt_text,
                        'post_title' => $alt_text
                    ]);

                    set_post_thumbnail($post_id, $attach_id);
                    file_put_contents($debug_log, "Image attached successfully (ID: $attach_id)\n\n", FILE_APPEND);
                } else {
                    file_put_contents($debug_log, "Attachment insert failed: " . $attach_id->get_error_message() . "\n", FILE_APPEND);
                }
            } catch (Throwable $e) {
                file_put_contents($debug_log, "Image error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }

    wp_redirect(admin_url('admin.php?page=gemini-blog-writer&success=1'));
    exit;
}