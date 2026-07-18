=== FOTOhub AI — Image Generation & Editing ===
Contributors: fotohub
Tags: ai, image generation, background removal, upscale, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate AI images, remove backgrounds, upscale photos, and bulk-generate product images with FOTOhub AI platform.

== Description ==

FOTOhub AI connects your WordPress site to the [FOTOhub](https://fotohub.app) AI platform, giving you access to state-of-the-art image generation and editing tools directly from your WordPress admin.

= Features =

* **AI Image Generation** — Generate images from text prompts using models like Seedream 5.0, Flux, DALL-E 3, and more.
* **Background Removal** — Remove backgrounds from any image in your media library with one click.
* **Image Upscaling** — Upscale images 2x or 4x with AI-powered enhancement.
* **Bulk Generation** — Generate multiple images from a list of prompts or CSV file.
* **WooCommerce Integration** — Auto-generate product photos from product titles and descriptions.

= How It Works =

1. Sign up at [fotohub.app](https://fotohub.app) and get an API key.
2. Install and activate this plugin.
3. Enter your API key in Settings > FOTOhub AI.
4. Generate images from Media Library, edit products, or use the bulk tool.

= WooCommerce Support =

When WooCommerce is active, FOTOhub AI adds:

* A "FOTOhub AI" tab on product edit pages to generate product photos.
* A bulk action to generate AI photos for multiple products at once.
* Smart prompt generation from product titles and descriptions.

= Models Available =

* Seedream 5.0 (Recommended — best quality)
* Flux 1 Schnell (Fast generation)
* Flux 1 Dev (High quality)
* Stable Diffusion XL
* DALL-E 3
* And more — see your FOTOhub dashboard for the full list.

= Security =

* API keys are stored encrypted using AES-256-CBC.
* All AJAX requests use WordPress nonces.
* API calls are made server-side only — keys never exposed to the browser.
* All inputs sanitized, all outputs escaped.

== Installation ==

1. Upload the `fotohub-ai` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings > FOTOhub AI and enter your API key.
4. Click "Test Connection" to verify your setup.

= Get an API Key =

1. Visit [fotohub.app](https://fotohub.app) and create an account.
2. Navigate to Settings > API Keys in your FOTOhub dashboard.
3. Create a new API key and copy it.
4. Paste it into the WordPress plugin settings.

== Frequently Asked Questions ==

= Do I need a FOTOhub account? =

Yes, you need a FOTOhub account and API key. Sign up at [fotohub.app](https://fotohub.app).

= How much does it cost? =

The plugin is free. You pay per image generated through your FOTOhub account credits. See [pricing](https://fotohub.app/pricing) for details.

= Does it work with WooCommerce? =

Yes! When WooCommerce is active, the plugin adds product photo generation features automatically.

= What image formats are supported? =

Generated images are saved as PNG. Background removal and upscaling support JPG, PNG, and WebP inputs.

= Is my API key secure? =

Yes. API keys are encrypted with AES-256-CBC before storage. They are never sent to the browser — all API calls happen server-side through WordPress AJAX handlers.

= Can I use custom models? =

Yes. While the plugin provides a preset list, any model available on your FOTOhub plan can be used via the API.

== Screenshots ==

1. Settings page with API key configuration and connection test.
2. AI image generation modal in the Media Library.
3. Bulk generation tool with CSV support.
4. WooCommerce product photo generation panel.
5. Background removal in the media library.

== Changelog ==

= 1.0.0 =
* Initial release.
* AI image generation from text prompts.
* Background removal.
* Image upscaling (2x/4x).
* Bulk generation tool with CSV support.
* WooCommerce integration for product photos.
* Encrypted API key storage.

== Upgrade Notice ==

= 1.0.0 =
Initial release of FOTOhub AI for WordPress.
