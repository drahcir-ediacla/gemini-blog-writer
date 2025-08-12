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
2. Enter your **Gemini API Key** (for text content generation).
3. Toggle **Enable OpenAI Image Generation** if you want to automatically create featured images.
4. Enter your **OpenAI API Key** (for image generation).
5. Provide **Default RSS Feed URL** if you want to pull news content automatically.
6. Select **Gemini Model** (e.g., `gemini-2.5-pro`).
7. Choose **RSS Rewrite Mode**.
8. Enable **Auto-Posting** if you want scheduled publishing.
9. Set **Auto-Post Interval** (e.g., every 1 day).
10. Click **Save Settings**.

---

## 2. Manual Content Generation
1. Go to **Gemini Blog Writer → Generate Post**.
2. Choose **Content Source**:
   - **Use Custom Prompt** → Enter topic or idea.
   - **Use RSS Feed** → Fetches from your set feed.
3. Click **Generate**.
4. The plugin will:
   - Use Gemini API to create the article.
   - Optionally use OpenAI API to generate a featured image.
5. Fill in the **Title**, review or edit the **Content**.
6. Choose a **Category** and add **Tags**.
7. Click **Publish as Post**.

---

## 3. Automatic Content Generation
When **Auto-Posting** is enabled:
1. The plugin will fetch new articles from the provided **RSS Feed URL**.
2. Content is rewritten using the Gemini API.
3. A featured image will be generated if enabled.
4. The post will be automatically published according to your set **Auto-Post Interval**.

---

## 4. MailPoet Newsletter Integration
After publishing:
1. The plugin automatically creates a **MailPoet Newsletter** template using the post’s title, content, and featured image.
2. The newsletter is scheduled to send to subscribers after **5 minutes**.
3. Emails are sent to the target subscriber list configured in MailPoet.

---

## 5. Tips & Best Practices
- Use specific prompts when generating articles for higher quality results.
- Choose RSS feeds that provide rich, descriptive content for better rewriting.
- Monitor your API usage for Gemini and OpenAI to avoid hitting limits.
- Test MailPoet sending with a small subscriber segment before sending to all.

---

## 6. Troubleshooting
- **No Image Generated** → Ensure "Enable OpenAI Image Generation" is turned on and your OpenAI API key is valid.
- **No Auto Posts** → Check that "Enable Auto-Posting" is enabled, interval is set, and RSS feed is accessible.
- **MailPoet Not Sending** → Confirm MailPoet is active and properly configured with a sending method (SMTP, MailPoet sending service, etc.).


