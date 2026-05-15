# WP Bunny Stream

> 🇬🇧 English · [🇫🇷 Français](README.fr.md) · [🇸🇮 Slovenščina](README.sl.md)

Self-hosted bridge between **WordPress** and **[Bunny Stream](https://bunny.net/stream/)** — upload very large videos, manage them as a custom post type, generate chapters / moments / subtitles with AI, and embed a fully customizable player.

## Features

- **Custom post type** `bunny_video` with built-in **categories** and **tags** taxonomies.
- **Resumable TUS upload** — multi-gigabyte files, no PHP `upload_max_filesize` issues.
- **Manual GUID linking** — attach any existing Bunny video to a WordPress post.
- **Auto thumbnail** — featured image pulled from Bunny CDN.
- **Webhook receiver** — status, captions, dimensions and AI events sync automatically.
- **Native Bunny player** with overridable defaults (autoplay, loop, muted, preload, accent color, start time).
- **Auto aspect ratio** detection (16:9, 9:16, 1:1, …) with portrait videos capped to a sensible width.
- **Shortcode** `[bunny_video id="42"]`.
- **Gutenberg block** with live search picker and player options.
- **Beaver Builder module** with custom autocomplete picker.
- **AI features**:
  - Generate chapters and moments only (free, when a transcription exists).
  - Transcribe + generate captions + title + description + chapters + moments. Multi-language with **Polylang** integration.
- **Editors** for chapters, moments and captions (VTT / SRT). Inline VTT editor for fixing typos.

## Installation

1. Download or clone this repository into `wp-content/plugins/wp-bunny-stream`.
2. Activate **WP Bunny Stream** in WordPress.
3. Go to **Bunny Videos → Settings** and enter:
   - **Library ID** (numeric, e.g. `661274`)
   - **API key** (from Stream → your library → API)
   - **CDN hostname** (optional, used for thumbnails and caption fetching, e.g. `vz-xxxxx.b-cdn.net`)
4. (Optional) Add the webhook URL shown on the settings page to your Bunny library so post status updates automatically. Set a shared secret in the same page and append `?secret=YOUR_SECRET` to the URL.

## Usage

### Upload a video
1. **Bunny Videos → Add New**.
2. Type the title, pick categories / tags.
3. Drag a video file into the meta box.
4. Upload progresses with resumable chunks. The Bunny GUID is saved on the post **before** TUS starts, so even an interrupted upload remains recoverable.
5. Click **Save / Publish** — the page does not auto-reload.

### Link an existing Bunny video
If a video already exists on Bunny (uploaded via the dashboard or another tool), use the **Link an existing Bunny video by GUID** section in the meta box. Paste the GUID, the plugin validates it via the API and pulls the metadata (duration, dimensions, thumbnail).

### AI: chapters, moments, captions
The **AI** panel of the meta box exposes two buttons:
- **Generate chapters + moments** — calls `/smart`. Free if the video already has a transcription.
- **Transcribe + generate all (paid)** — calls `/transcribe`. Triggers transcription, translation to target languages (Polylang-aware), and AI chapters/moments. Charged at **$0.10 per minute per language** by Bunny.

Once Bunny finishes processing (asynchronously), click **Refresh status** to pull the results into the meta box editors.

### Manually editing
- **Chapters** — grid of *title / start / end* rows. Accept `mm:ss`, `hh:mm:ss` or raw seconds. Sorted by start time on save and pushed to Bunny.
- **Moments** — *label / timestamp*. Shown as markers on the Bunny player timeline.
- **Captions** — list per language with **Edit** (inline VTT editor, content fetched from the CDN), **Delete**, **+ Add caption** (file upload `.vtt` / `.srt` or paste content).

### Embedding
| Method | Example |
|---|---|
| Shortcode | `[bunny_video id="42" autoplay="1" ratio="9:16"]` |
| Gutenberg block | *Bunny Video* — pick via search box |
| Beaver Builder | *Bunny Video* module — search-as-you-type picker |
| PHP | `echo WPBS_Shortcode::render( [ 'id' => 42 ] );` |

#### Shortcode attributes

| Attribute | Default | Description |
|---|---|---|
| `id` | – | Post ID of a `bunny_video` post |
| `guid` | – | Direct Bunny GUID (overrides `id`) |
| `autoplay` / `loop` / `muted` / `preload` | global default | Overrides player options |
| `color` | global default | Hex color for player accent |
| `t` | – | Start time in seconds |
| `ratio` | auto | `auto`, `16:9`, `9:16`, `1:1`, `4:3`, `21:9`, `4:5`, or `H/W` decimal |
| `width` | – | Max width (px or %) |

## Polylang

If Polylang is installed, language selectors in the AI panel are populated with your Polylang languages and the post's current language is pre-selected as transcription source. Without Polylang, the WordPress site locale is used.

## Webhook

Endpoint: `https://yoursite.tld/wp-json/wp-bunny-stream/v1/webhook`

Validation order:
1. If a shared secret is configured, the request must include `?secret=YOUR_SECRET`.
2. Otherwise the plugin validates HMAC-SHA256 of the raw body using the library Read-only API key, sent by Bunny via `X-BunnyStream-Signature`.

Status codes handled (`3` Finished, `4` Resolution finished, `9` Captions generated, `10` Title/description generated) trigger a full sync of dimensions, duration, captions, chapters, moments and thumbnail.

## Hooks / filters

The plugin keeps a flat surface area on purpose. Reusable entry points:
- `WPBS_Bunny_API::*` — public methods to call any Bunny endpoint from your own code.
- `WPBS_Shortcode::render( $atts )` — render the iframe HTML directly.
- `WPBS_Webhook::sync_video_to_post( $post_id, $video_array )` — re-use the sync logic.

Custom meta keys (registered via `register_post_meta` for `show_in_rest`):
`_wpbs_video_guid`, `_wpbs_library_id`, `_wpbs_status`, `_wpbs_duration`, `_wpbs_width`, `_wpbs_height`, `_wpbs_thumbnail_url`, `_wpbs_chapters`, `_wpbs_moments`, `_wpbs_captions`, `_wpbs_description`, `_wpbs_smart_status`, `_wpbs_player_override`.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Module renders empty | A red diagnostic banner appears for admins / inside Beaver Builder showing exactly what is missing (no GUID, no library, no selection). |
| Beaver Builder picker empty | Make sure videos are listed in **Bunny Videos**. The picker shows drafts with `[draft]` suffix and orphaned posts with `⚠ no upload`. |
| Upload completed on Bunny but not on WP | Open the post, use **Link an existing Bunny video by GUID** to pair it. |
| AI panel shows no language | Install **Polylang** or set your WordPress site language. |

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Bunny Stream library

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
