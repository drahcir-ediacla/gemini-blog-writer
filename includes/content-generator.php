<?php
if (!defined('ABSPATH')) {
    exit;
}

function gemini_generate_content_from_rss($rss_url)
{
    include_once ABSPATH . WPINC . '/feed.php';
    $rss = fetch_feed($rss_url);
    if (is_wp_error($rss)) {
        error_log("RSS fetch error: " . $rss->get_error_message());
        return null;
    }

    $rss_mode = get_option('gemini_rss_mode', '3');
    $items_count = $rss_mode === 'latest' ? 1 : intval($rss_mode);
    $maxitems = $rss->get_item_quantity($items_count);
    $rss_items = $rss->get_items(0, $maxitems);

    $api_key = get_option('gemini_api_key', '');
    $model = get_option('gemini_model', 'gemini-1.5-flash');

    $formatting_instructions = "Write a complete blog post in HTML format. Use <h4> for headings, <p> for paragraphs, <strong> for emphasis, and lists where appropriate. Do not include <html>, <title>, <head>, or <body> tags. Do not wrap the HTML in code blocks — return raw HTML only.\n\n";

    $rss_summary = $formatting_instructions . "Summarize or rewrite the following articles into a single blog post.\n\n";

    foreach ($rss_items as $item) {
        $rss_summary .= "Title: " . $item->get_title() . "\n";
        $rss_summary .= "Content: " . strip_tags($item->get_description()) . "\n\n";
    }

    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    $body = json_encode([
        'contents' => [['parts' => [['text' => $rss_summary]]]]
    ]);

    $response = wp_remote_post("{$endpoint}?key={$api_key}", [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $body,
        'method' => 'POST',
        'timeout' => 60
    ]);

    $log_file = __DIR__ . '/gemini-debug.log';

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        file_put_contents($log_file, "API request failed: " . $error_message . "\n", FILE_APPEND);
        return null;
    }

    $data_body = wp_remote_retrieve_body($response);
    file_put_contents($log_file, "API raw response: " . $data_body . "\n\n", FILE_APPEND);

    $data = json_decode($data_body, true);

    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        file_put_contents($log_file, "API returned no text.\n", FILE_APPEND);
        return null;
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'];

    // Remove code block markers if present
    $text = preg_replace('/^```html\s*/i', '', $text);
    $text = preg_replace('/```$/', '', $text);

    if (preg_match('/<h2>(.*?)<\/h2>/i', $text, $matches)) {
        $title = wp_strip_all_tags($matches[1]);
        $content_without_title = preg_replace('/<h2>.*?<\/h2>/i', '', $text, 1);
    } else {
       // Fallback: use first line as title
        $lines = explode("\n", $text);
        $first_line = trim(array_shift($lines));
        $title = wp_strip_all_tags($first_line);
        $content_without_title = implode("\n", $lines);
    }

    return ['title' => $title, 'content' => $content_without_title];
}