# Gemini Blog Writer – User Guide

## Overview
The **Gemini Blog Writer** plugin allows you to create blog/news content using:
- **Gemini API** for text content.
- **OpenAI API** for featured image generation.
- **MailPoet** integration to send newsletters after publishing.

### Features:
1. Generate articles using prompt or RSS feed.
2. Schedule automatic posting from RSS.
3. Auto-send MailPoet newsletters after publishing.

---

## 1. Installation & Setup

**Step 1 – Activate the Plugin:**
1. Go to **WordPress Dashboard → Plugins → Add New**.
2. Upload the plugin ZIP file.
3. Click **Install Now**, then **Activate**.

**Step 2 – Configure API Keys:**
1. Go to **Gemini Blog Writer → Settings**.
2. Enter your **Gemini API Key**.
3. Toggle **Enable OpenAI Image Generation** if desired.
4. Enter your **OpenAI API Key**.
5. Provide **Default RSS Feed URL**.
6. Select **Gemini Model** (e.g., `gemini-2.5-pro`).
7. Choose **RSS Rewrite Mode**.
8. Enable **Auto-Posting**.
9. Set **Auto-Post Interval**.
10. Click **Save Settings**.

---

## 2. Manual Content Generation
1. Go to **Gemini Blog Writer → Generate Post**.
2. Choose **Content Source**:
   - **Use Custom Prompt** → Enter topic or idea.
   - **Use RSS Feed** → Fetches from your set feed.
3. Click **Generate**.
4. Review the generated content.
5. Fill in **Title**, **Category**, and **Tags**.
6. Click **Publish as Post**.

---

## 3. Automatic Content Generation
When **Auto-Posting** is enabled:
1. The plugin fetches new articles from the RSS feed.
2. Content is rewritten using the Gemini API.
3. A featured image is generated if enabled.
4. The post is published according to your **Auto-Post Interval**.

---

## 4. MailPoet Newsletter Integration
After publishing:
1. The plugin creates a **MailPoet Newsletter** template.
2. The newsletter is scheduled to send **after 5 minutes**.
3. Sent to the subscriber list configured in MailPoet.

---

## 5. Tips & Best Practices
- Use specific prompts for better results.
- Choose quality RSS feeds.
- Monitor your API usage.
- Test MailPoet sending before going live.

---

## 6. Troubleshooting
- **No Image Generated** → Check OpenAI settings.
- **No Auto Posts** → Ensure Auto-Posting and RSS feed are set.
- **MailPoet Not Sending** → Verify MailPoet configuration.


