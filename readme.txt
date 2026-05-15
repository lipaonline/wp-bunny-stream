=== WP Bunny Stream ===
Contributors: lipa
Tags: video, bunny stream, bunnycdn, hls, streaming, video hosting
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Upload large videos to Bunny Stream with a dedicated CPT, taxonomies, customizable player, shortcode, Gutenberg block and Beaver Builder module.

== Description ==

Lightweight bridge between WordPress and Bunny Stream:

* Custom post type **Bunny Videos** with categories & tags taxonomies
* Resumable (TUS) chunked upload — handles large files (multi-GB) without hitting PHP upload limits
* Webhook receiver for processing status + automatic featured image from Bunny thumbnail
* Global + per-video player options (autoplay, loop, muted, preload, accent color, start time)
* `[bunny_video id="42"]` shortcode
* Gutenberg block
* Beaver Builder module

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wp-bunny-stream` and activate.
2. Go to **Bunny Videos → Settings** and paste your Bunny Stream **Library ID** and **API key**.
3. (Optional) Set your CDN hostname to enable thumbnail fetching.
4. (Optional) Add the webhook URL shown in settings to your Bunny library so post status updates automatically.
5. Create a new **Bunny Video** post and drag in a video file.

== Changelog ==

= 1.0.0 =
* Initial release.
