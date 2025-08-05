<?php
if (!defined('ABSPATH')) exit;

// 📦 Reusable helper function for generating DALL·E image
function gemini_generate_dalle_image_base64($title, $api_key = null) {
    if (!$title) return null;
    if (!$api_key) $api_key = get_option('openai_api_key');
    if (!$api_key) return null;

    $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'prompt' => "A high-quality blog illustration about: " . $title,
            'n' => 1,
            'size' => '512x512',
            'response_format' => 'b64_json'
        ]),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        error_log("OpenAI image generation error: " . $response->get_error_message());
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return $body['data'][0]['b64_json'] ?? null;
}

// 🔁 AJAX handler using the shared function
add_action('wp_ajax_generate_dalle_image', 'generate_dalle_image_ajax');

function generate_dalle_image_ajax() {
    $title = sanitize_text_field($_POST['title'] ?? '');
    $base64 = gemini_generate_dalle_image_base64($title);

    if (!$title) {
        wp_send_json_error(['message' => 'Missing title']);
    }

    if (!$base64) {
        wp_send_json_error(['message' => 'Image generation failed or API key missing']);
    }

    wp_send_json_success(['base64_image' => $base64]);
}
