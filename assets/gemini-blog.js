document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('gemini_auto_posting');
    const intervalOptions = document.getElementById('interval-options');
    if (toggle) {
        toggle.addEventListener('change', () => {
            intervalOptions.style.display = toggle.checked ? 'block' : 'none';
        });
        intervalOptions.style.display = toggle.checked ? 'block' : 'none';
    }

    document.getElementById('generate-blog').addEventListener('click', async () => {
        const prompt = document.getElementById('gemini-prompt').value;
        const apiKey = document.getElementById('vertex-api-key')?.value || '';
        const mode = document.getElementById('generation-mode').value;
        const rssUrl = document.getElementById('rss-url').value;

        const generateButton = document.getElementById('generate-blog');
        const loadingIndicator = document.getElementById('generate-loading');
        generateButton.disabled = true;
        loadingIndicator.style.display = 'inline';

        const form = new FormData();
        form.append('action', 'gemini_generate_content');
        form.append('apiKey', apiKey);
        form.append('prompt', prompt);
        form.append('mode', mode);
        form.append('rssUrl', rssUrl);

        const response = await fetch(ajaxurl, {
            method: 'POST',
            body: form
        });

        const data = await response.json();

        // Populate content
        if (typeof tinyMCE !== 'undefined') {
            let editor = tinyMCE.get('generated-content');
            if (!editor && tinymce.activeEditor?.id === 'generated-content') {
                editor = tinymce.activeEditor;
            }
            if (editor) {
                editor.setContent(data.content || data.error || 'No result.', { format: 'html' });
            } else {
                const textarea = document.getElementById('generated-content');
                if (textarea) textarea.value = data.content || data.error || 'No result.';
            }
        }

        // Generate image if title exists and openAI key enabled
        if (data.title) {
            document.getElementById('post-title').value = data.title;

            if (enableOpenAI) {
                generateDalleImage(data.title);
            }
        }

        generateButton.disabled = false;
        loadingIndicator.style.display = 'none';
    });

    // 🔁 Reusable DALL·E image generation logic
    async function generateDalleImage(title) {
        const imageForm = new FormData();
        imageForm.append('action', 'generate_dalle_image');
        imageForm.append('title', title);

        let previewContainer = document.getElementById('image-preview-container');
        if (!previewContainer) {
            previewContainer = document.createElement('div');
            previewContainer.id = 'image-preview-container';
            document.getElementById('gemini-blog-form').appendChild(previewContainer);
        }

        // Loading fallback UI
        previewContainer.innerHTML = `
      <p><strong>Generating image preview...</strong></p>
      <div style="width:300px;height:300px;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;">
        <span style="font-style:italic;color:#888;">Loading image...</span>
      </div>`;

        try {
            const res = await fetch(ajaxurl, {
                method: 'POST',
                body: imageForm
            });

            const result = await res.json();

            if (result.success && result.data?.base64_image) {
                // Update hidden input with base64
                let hiddenInput = document.getElementById('featured-image-base64');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'featured_image_base64';
                    hiddenInput.id = 'featured-image-base64';
                    document.getElementById('gemini-blog-form').appendChild(hiddenInput);
                }
                hiddenInput.value = result.data.base64_image;

                // Show image + Re-generate button
                previewContainer.innerHTML = `
          <p><strong>Featured Image Preview:</strong></p>
          <img 
            src="data:image/webp;base64,${result.data.base64_image}" 
            alt="Illustration of ${title}" 
            title="Illustration of ${title}"
            style="max-width:300px; border:1px solid #ccc;" />
          <br/>
          <button type="button" id="regenerate-image-btn" style="margin-top:10px;">🔄 Re-generate Image</button>
        `;

                document.getElementById('regenerate-image-btn').addEventListener('click', () => {
                    generateDalleImage(title);
                });

            } else {
                previewContainer.innerHTML = `<p style="color:red;">❌ Failed to generate image preview.</p>`;
                console.error('Image generation failed:', result);
            }
        } catch (err) {
            previewContainer.innerHTML = `<p style="color:red;">❌ Image generation error.</p>`;
            console.error('Image generation error:', err);
        }
    }
});
