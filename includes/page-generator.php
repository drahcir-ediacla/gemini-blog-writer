<?php
if (!defined('ABSPATH')) {
    exit;
}

function gemini_blog_generator_page()
{
    $categories = get_categories(['hide_empty' => false]);
    $api_key = get_option('gemini_api_key', '');
    $rss_url = get_option('gemini_rss_url', '');

    ?>
    <div class="wrap">
        <h1>Generate Blog Post with Gemini</h1>

        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ Post published successfully!</p>
            </div>
        <?php endif; ?>

        <form id="gemini-blog-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="gemini_publish_post">
            <?php wp_nonce_field('gemini_publish_post_nonce', 'gemini_nonce_field'); ?>

            <label for="vertex-api-key">Gemini API Key:</label><br>
            <input type="text" id="vertex-api-key" name="vertex-api-key" size="80" value="<?php echo esc_attr($api_key); ?>"
                readonly><br><br>

            <label for="generation-mode">Content Source:</label><br>
            <select id="generation-mode" name="generation-mode">
                <option value="prompt">Use Custom Prompt</option>
                <option value="rss">Use RSS Feed</option>
            </select><br><br>

            <div id="prompt-options">
                <label for="gemini-prompt">Enter Prompt:</label><br>
                <textarea id="gemini-prompt" rows="6" cols="80" placeholder="Enter a topic or idea..."></textarea><br><br>
            </div>

            <div id="rss-options" style="display:none;">
                <label for="rss-url">RSS Feed URL:</label><br>
                <input type="text" id="rss-url" name="rss-url" size="80" value="<?php echo esc_attr($rss_url); ?>"
                    readonly><br><br>
            </div>

            <button type="button" id="generate-blog">Generate</button>
            <span id="generate-loading" style="display:none;">⏳ Generating...</span><br><br>

            <label for="post-title">Title:</label><br>
            <input type="text" id="post-title" name="title" size="80"><br><br>

            <div id="image-preview-container" style="margin-bottom: 20px;"></div>
            <input type="hidden" name="featured_image_base64" id="featured-image-base64" />

            <label for="generated-content">Content:</label><br>
            <?php
            wp_editor('', 'generated-content', [
                'textarea_name' => 'content',
                'textarea_rows' => 15,
                'media_buttons' => true,
                'tinymce' => true,
            ]);
            ?><br>
            <label for="category">Category:</label><br>
            <select id="category" name="category">
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <label for="tags">Tags (comma-separated):</label><br>
            <input type="text" id="tags" name="tags" size="80"><br><br>

            <button type="submit" id="publish-button">Publish as Post</button>
            <span id="publish-loading" style="display:none;">📤 Publishing...</span>
        </form>
    </div>
    <script src="<?php echo plugin_dir_url(__FILE__) . '../assets/gemini-blog.js'; ?>"></script>

    <script>
        document.getElementById('generation-mode').addEventListener('change', (e) => {
            const mode = e.target.value;
            document.getElementById('prompt-options').style.display = mode === 'prompt' ? 'block' : 'none';
            document.getElementById('rss-options').style.display = mode === 'rss' ? 'block' : 'none';
        });
    </script>
    <script>
        const enableOpenAI = <?php echo get_option('enable_openai_image', 'no') === 'yes' ? 'true' : 'false'; ?>;
    </script>

    <?php
}
